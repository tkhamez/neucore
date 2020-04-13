<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use GuzzleHttp\Psr7\Response;
use Neucore\Entity\Corporation;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\RemovedCharacterRepository;
use Neucore\Service\Account;
use Neucore\Service\AutoGroupAssignment;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use Neucore\Service\ObjectManager;
use Neucore\Service\UserAuth;
use Neucore\Middleware\Psr15\Session\SessionData;
use Brave\Sso\Basics\EveAuthentication;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\Logger;

class UserAuthTest extends TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var \Doctrine\Persistence\ObjectManager
     */
    private $om;

    /**
     * @var Logger
     */
    private $log;

    /**
     * @var UserAuth
     */
    private $service;

    /**
     * @var RemovedCharacterRepository
     */
    private $removedCharRepo;

    /**
     * @var Client
     */
    private $client;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();

        $this->helper->resetSessionData();
        $_SESSION = []; // "start" session for SessionData object and reset data

        $this->log = new Logger('test');
        $this->om = $this->helper->getObjectManager();

        $repoFactory = new RepositoryFactory($this->om);

        $objManager = new ObjectManager($this->om, $this->log);

        $config = new Config(['eve' => ['datasource' => '', 'esi_host' => '']]);
        $this->client = new Client();
        $esi = new EsiData(
            $this->log,
            new EsiApiFactory($this->client, $config),
            $objManager,
            $repoFactory,
            $config
        );
        $autoGroups = new AutoGroupAssignment($objManager, $repoFactory, $this->log);

        $characterService = new Account($this->log, $objManager, $repoFactory, $esi, $autoGroups);
        $this->service = new UserAuth(
            new SessionData(),
            $characterService,
            $repoFactory,
            $this->log
        );

        $this->removedCharRepo = $repoFactory->getRemovedCharacterRepository();
    }

    public function testGetRolesNoAuth()
    {
        $roles = $this->service->getRoles();
        $this->assertSame([Role::ANONYMOUS], $roles);
    }

    public function testGetRoles()
    {
        $this->helper->addCharacterMain('Test User', 9013, [Role::USER, Role::GROUP_MANAGER]);
        $_SESSION['character_id'] = 9013;

        $roles = $this->service->getRoles();

        $this->assertSame([Role::USER, Role::GROUP_MANAGER], $roles);
    }

    public function testGetUserNoAuth()
    {
        $user = $this->service->getUser();
        $this->assertNull($user);
    }

    public function testGetUser()
    {
        $this->helper->addCharacterMain('Test User', 9013, [Role::USER, Role::GROUP_MANAGER]);
        $_SESSION['character_id'] = 9013;

        $user = $this->service->getUser();

        $this->assertSame(9013, $user->getId());
    }

    public function testAuthenticateNoUserRoleError()
    {
        $token = new AccessToken(['access_token' => 'token']);
        $this->assertFalse($this->service->authenticate(
            new EveAuthentication(888, 'New User', 'char-owner-hash', $token)
        ));
        $this->assertSame(
            'UserAuth::authenticate(): Role "'.Role::USER.'" not found.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    /**
     * @throws \Exception
     */
    public function testAuthenticateNewUser()
    {
        $this->helper->addRoles([Role::USER]);
        SessionData::setReadOnly(false);

        $this->assertFalse(isset($_SESSION['character_id']));

        $this->client->setResponse(
            new Response(200, [], '{"name": "New User", "corporation_id": 102}'), // getCharactersCharacterId
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}') // getCorporationsCorporationId()
        );

        $accessToken = Helper::generateToken()[0];
        $token = new AccessToken(
            ['access_token' => $accessToken, 'expires' => 1525456785, 'refresh_token' => 'refresh']
        );
        $result = $this->service->authenticate(new EveAuthentication(888, 'New User', 'coh', $token));

        $this->om->clear();

        $user = $this->service->getUser();
        $this->assertTrue($result);
        $this->assertSame('New User', $user->getName());
        $this->assertSame(888, $user->getId());
        $this->assertTrue($user->getMain());
        $this->assertSame('coh', $user->getCharacterOwnerHash());
        $this->assertSame($accessToken, $user->getAccessToken());
        $this->assertSame(1525456785, $user->getExpires());
        $this->assertSame('refresh', $user->getRefreshToken());
        $this->assertTrue($user->getValidToken());
        $this->assertSame($_SESSION['character_id'], $user->getId());
        $this->assertSame([Role::USER], $this->service->getRoles());
        $this->assertSame('UTC', $user->getLastLogin()->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), $user->getLastLogin()->getTimestamp(), 10);
    }

    /**
     * @throws \Exception
     */
    public function testAuthenticateExistingUser()
    {
        SessionData::setReadOnly(false);
        $corp = (new Corporation())->setId(101);
        $this->om->persist($corp);
        $char = $this->helper->addCharacterMain('Test User', 9013, [Role::USER, Role::GROUP_MANAGER]);
        $char->setCorporation($corp);
        $player = $char->getPlayer();

        $this->assertSame('123', $char->getCharacterOwnerHash());
        $this->assertSame('abc', $char->getAccessToken());
        $this->assertSame(123456, $char->getExpires());
        $this->assertSame('def', $char->getRefreshToken());
        $this->assertNull($char->getValidToken());
        $this->assertNull($char->getLastLogin());

        $accessToken = Helper::generateToken()[0];
        $token = new AccessToken(
            ['access_token' => $accessToken, 'expires' => 1525456785, 'refresh_token' => 'refresh']
        );
        $result = $this->service->authenticate(new EveAuthentication(9013, 'Test User Changed Name', '123', $token));

        $user = $this->service->getUser();
        $this->assertTrue($result);
        $this->assertSame(9013, $_SESSION['character_id']);
        $this->assertSame(9013, $user->getId());
        $this->assertSame('Test User Changed Name', $user->getName());
        $this->assertSame('123', $user->getCharacterOwnerHash());
        $this->assertSame($accessToken, $user->getAccessToken());
        $this->assertSame(1525456785, $user->getExpires());
        $this->assertSame('refresh', $user->getRefreshToken());
        $this->assertTrue($char->getValidToken());
        $this->assertSame('UTC', $user->getLastLogin()->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), $user->getLastLogin()->getTimestamp(), 10);
        $this->assertSame($user->getPlayer()->getId(), $player->getId());
    }

    public function testAuthenticateNewOwner()
    {
        SessionData::setReadOnly(false);
        $corp = (new Corporation())->setId(101);
        $this->om->persist($corp);
        $char1 = $this->helper->addCharacterMain('Test User1', 9013, [Role::USER, Role::GROUP_MANAGER]);
        $player = $char1->getPlayer();
        $char2 = $this->helper->addCharacterToPlayer('Test User2', 9014, $player);
        $char2->setCorporation($corp);

        $this->assertSame(9014, $char2->getId());
        $this->assertSame('456', $char2->getCharacterOwnerHash());
        $this->assertSame($char2->getPlayer()->getId(), $player->getId());

        // changed hash 789, was 456
        $token = new AccessToken(['access_token' => 'token', 'expires' => 1525456785, 'refresh_token' => 'refresh']);
        $result = $this->service->authenticate(new EveAuthentication(9014, 'Test User2', '789', $token));

        $user = $this->service->getUser();
        $newPlayer = $user->getPlayer();

        $this->assertTrue($result);
        $this->assertSame(9014, $_SESSION['character_id']);
        $this->assertSame(9014, $user->getId());
        $this->assertSame('789', $user->getCharacterOwnerHash());
        $this->assertNotSame($newPlayer->getId(), $player->getId());

        // check RemovedCharacter
        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 9014]);
        $this->assertSame($user->getId(), $removedChar->getcharacterId());
        $this->assertSame($player->getId(), $removedChar->getPlayer()->getId());
        $this->assertSame($newPlayer->getId(), $removedChar->getNewPlayer()->getId());
        $this->assertSame(RemovedCharacter::REASON_MOVED, $removedChar->getReason());
    }

    public function testAddAltNoRefreshToken()
    {
        $_SESSION['character_id'] = 100;
        $main = $this->helper->addCharacterMain('Main', 100, [Role::USER]);
        $player = $main->getPlayer();

        $this->assertSame(1, count($player->getCharacters()));

        $this->client->setResponse(
            new Response(200, [], '{"name": "Alt 1", "corporation_id": 102}'), // getCharactersCharacterId
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}') // getCorporationsCorporationId()
        );

        $token = new AccessToken(['access_token' => 'tk', 'expires' => 1525456785]);
        $result = $this->service->addAlt(new EveAuthentication(101, 'Alt 1', 'hash', $token));
        $this->assertTrue($result);

        $chars = $player->getCharacters();
        $this->assertSame(2, count($chars));

        $this->assertSame(101, $chars[1]->getId());
        $this->assertLessThanOrEqual(time(), $chars[1]->getCreated()->getTimestamp());
        $this->assertSame('Alt 1', $chars[1]->getName());
        $this->assertSame('hash', $chars[1]->getCharacterOwnerHash());
        $this->assertSame('tk', $chars[1]->getAccessToken());
        $this->assertSame(1525456785, $chars[1]->getExpires());
        $this->assertSame(null, $chars[1]->getRefreshToken());
        $this->assertNull($chars[1]->getValidToken());
        $this->assertFalse($chars[1]->getMain());
    }

    /**
     * @throws \Exception
     */
    public function testAddAltExistingChar()
    {
        $_SESSION['character_id'] = 100;
        $corp = (new Corporation())->setId(101);
        $this->om->persist($corp);
        $main1 = $this->helper->addCharacterMain('Main1', 100, [Role::USER]);
        $main2 = $this->helper->addCharacterMain('Main2', 200, [Role::USER]);
        $main2->setCorporation($corp);
        $newPlayerId = $main1->getPlayer()->getId();
        $oldPlayerId = $main2->getPlayer()->getId();

        $accessToken = Helper::generateToken()[0];
        $token = new AccessToken(['access_token' => $accessToken, 'expires' => 1525456785, 'refresh_token' => 'rf']);
        $result = $this->service->addAlt(new EveAuthentication(200, 'Main2 renamed', 'hash', $token));
        $this->assertTrue($result);

        $chars = $main1->getPlayer()->getCharacters();
        $this->assertSame(2, count($chars));

        $this->assertSame($main2, $chars[1]);
        $this->assertSame('Main2 renamed', $chars[1]->getName());
        $this->assertSame('hash', $chars[1]->getCharacterOwnerHash());
        $this->assertSame($accessToken, $chars[1]->getAccessToken());
        $this->assertSame(1525456785, $chars[1]->getExpires());
        $this->assertSame('rf', $chars[1]->getRefreshToken());
        $this->assertTrue($chars[1]->getValidToken());

        // check RemovedCharacter
        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 200]);
        $this->assertSame($main2->getId(), $removedChar->getcharacterId());
        $this->assertNotSame($main2->getPlayer()->getId(), $removedChar->getPlayer()->getId());
        $this->assertNotNull($main2->getPlayer()->getId());
        $this->assertSame($oldPlayerId, $removedChar->getPlayer()->getId());
        $this->assertNotNull($oldPlayerId);
        $this->assertSame($newPlayerId, $removedChar->getNewPlayer()->getId());
        $this->assertSame(RemovedCharacter::REASON_MOVED, $removedChar->getReason());
        $this->assertNotNull($newPlayerId);
    }

    public function testAddAltLoggedInChar()
    {
        $_SESSION['character_id'] = 100;
        $main = $this->helper->addCharacterMain('Main1', 100, [Role::USER]);
        $corp = (new Corporation())->setId(101);
        $this->om->persist($corp);
        $main->setCorporation($corp);

        $token = new AccessToken(['access_token' => 'tk']);
        $result = $this->service->addAlt(new EveAuthentication(100, 'Main1 renamed', 'hash', $token));
        $this->assertTrue($result);

        $chars = $main->getPlayer()->getCharacters();
        $this->assertSame(1, count($chars));
        $this->assertSame('Main1 renamed', $chars[0]->getName()); // name changed
    }

    public function testAddAltNotAuthenticated()
    {
        $this->helper->addCharacterMain('Main1', 100, [Role::USER]);

        $token = new AccessToken(['access_token' => 'tk']);
        $result = $this->service->addAlt(new EveAuthentication(100, 'Main1 renamed', 'hash', $token, []));
        $this->assertFalse($result);
    }
}
