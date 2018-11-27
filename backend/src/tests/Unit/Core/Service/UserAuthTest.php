<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Role;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\RemovedCharacterRepository;
use Brave\Core\Service\CharacterService;
use Brave\Core\Service\ObjectManager;
use Brave\Core\Service\UserAuth;
use Brave\Slim\Session\SessionData;
use Brave\Sso\Basics\EveAuthentication;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Tests\Helper;
use Tests\Logger;

class UserAuthTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var EntityManagerInterface
     */
    private $em;

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

    public function setUp()
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();

        $this->helper->resetSessionData();
        $_SESSION = []; // "start" session for SessionData object and reset data

        $this->log = new Logger('test');
        $this->em = $this->helper->getEm();

        $repoFactory = new RepositoryFactory($this->em);

        $objManager = new ObjectManager($this->em, $this->log);
        $characterService = new CharacterService($this->log, $objManager);
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
            new EveAuthentication(888, 'New User', 'char-owner-hash', $token, [])
        ));
        $this->assertSame(
            'UserAuth::authenticate(): Role "'.Role::USER.'" not found.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testAuthenticateNewUser()
    {
        $this->helper->addRoles([Role::USER]);
        (new SessionData())->setReadOnly(false);

        $this->assertFalse(isset($_SESSION['character_id']));

        $token = new AccessToken(['access_token' => 'token', 'expires' => 1525456785, 'refresh_token' => 'refresh']);
        $result = $this->service->authenticate(new EveAuthentication(888, 'New User', 'coh', $token, ['scope1', 's2']));

        $this->em->clear();

        $user = $this->service->getUser();
        $this->assertTrue($result);
        $this->assertSame('New User', $user->getName());
        $this->assertSame(888, $user->getId());
        $this->assertTrue($user->getMain());
        $this->assertSame('coh', $user->getCharacterOwnerHash());
        $this->assertSame('token', $user->getAccessToken());
        $this->assertSame(1525456785, $user->getExpires());
        $this->assertSame('refresh', $user->getRefreshToken());
        $this->assertTrue($user->getValidToken());
        $this->assertSame('scope1 s2', $user->getScopes());
        $this->assertSame($_SESSION['character_id'], $user->getId());
        $this->assertSame([Role::USER], $this->service->getRoles());
        $this->assertSame('UTC', $user->getLastLogin()->getTimezone()->getName());
        #$this->assertTrue((new \DateTime())->diff($user->getLastLogin())->format('%s') < 2);
    }

    public function testAuthenticateExistingUser()
    {
        (new SessionData())->setReadOnly(false);
        $char = $this->helper->addCharacterMain('Test User', 9013, [Role::USER, Role::GROUP_MANAGER]);
        $player = $char->getPlayer();

        $this->assertSame('123', $char->getCharacterOwnerHash());
        $this->assertSame('abc', $char->getAccessToken());
        $this->assertSame(123456, $char->getExpires());
        $this->assertSame('def', $char->getRefreshToken());
        $this->assertFalse($char->getValidToken());
        $this->assertNull($char->getLastLogin());

        $token = new AccessToken(['access_token' => 'token', 'expires' => 1525456785, 'refresh_token' => 'refresh']);
        $result = $this->service->authenticate(
            new EveAuthentication(9013, 'Test User Changed Name', '123', $token, ['scope1', 's2'])
        );

        $user = $this->service->getUser();
        $this->assertTrue($result);
        $this->assertSame(9013, $_SESSION['character_id']);
        $this->assertSame(9013, $user->getId());
        $this->assertSame('Test User Changed Name', $user->getName());
        $this->assertSame('123', $user->getCharacterOwnerHash());
        $this->assertSame('token', $user->getAccessToken());
        $this->assertSame('scope1 s2', $user->getScopes());
        $this->assertSame(1525456785, $user->getExpires());
        $this->assertSame('refresh', $user->getRefreshToken());
        $this->assertTrue($char->getValidToken());
        $this->assertSame('UTC', $user->getLastLogin()->getTimezone()->getName());
        #$this->assertTrue((new \DateTime())->diff($user->getLastLogin())->format('%s') < 2);
        $this->assertSame($user->getPlayer()->getId(), $player->getId());
    }

    public function testAuthenticateNewOwner()
    {
        (new SessionData())->setReadOnly(false);
        $char1 = $this->helper->addCharacterMain('Test User1', 9013, [Role::USER, Role::GROUP_MANAGER]);
        $player = $char1->getPlayer();
        $char2 = $this->helper->addCharacterToPlayer('Test User2', 9014, $player);

        $this->assertSame(9014, $char2->getId());
        $this->assertSame('456', $char2->getCharacterOwnerHash());
        $this->assertSame($char2->getPlayer()->getId(), $player->getId());

        // changed hash 789, was 456
        $token = new AccessToken(['access_token' => 'token', 'expires' => 1525456785, 'refresh_token' => 'refresh']);
        $result = $this->service->authenticate(
            new EveAuthentication(9014, 'Test User2', '789', $token, ['scope1', 's2'])
        );

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
        $this->assertSame('moved', $removedChar->getAction());
    }

    public function testAddAlt()
    {
        $_SESSION['character_id'] = 100;
        $main = $this->helper->addCharacterMain('Main', 100, [Role::USER]);
        $player = $main->getPlayer();

        $this->assertSame(1, count($player->getCharacters()));

        $token = new AccessToken(['access_token' => 'tk', 'expires' => 1525456785, 'refresh_token' => 'rf']);
        $result = $this->service->addAlt(new EveAuthentication(101, 'Alt 1', 'hash', $token, ['scope1', 's2']));
        $this->assertTrue($result);

        $chars = $player->getCharacters();
        $this->assertSame(2, count($chars));

        $this->assertSame(101, $chars[1]->getId());
        $this->assertSame('Alt 1', $chars[1]->getName());
        $this->assertSame('hash', $chars[1]->getCharacterOwnerHash());
        $this->assertSame('tk', $chars[1]->getAccessToken());
        $this->assertSame('scope1 s2', $chars[1]->getScopes());
        $this->assertSame(1525456785, $chars[1]->getExpires());
        $this->assertSame('rf', $chars[1]->getRefreshToken());
        $this->assertTrue($chars[1]->getValidToken());
        $this->assertFalse($chars[1]->getMain());
    }

    public function testAddAltExistingChar()
    {
        $_SESSION['character_id'] = 100;
        $main1 = $this->helper->addCharacterMain('Main1', 100, [Role::USER]);
        $main2 = $this->helper->addCharacterMain('Main2', 200, [Role::USER]);
        $newPlayerId = $main1->getPlayer()->getId();
        $oldPlayerId = $main2->getPlayer()->getId();

        $token = new AccessToken(['access_token' => 'tk', 'expires' => 1525456785, 'refresh_token' => 'rf']);
        $result = $this->service->addAlt(new EveAuthentication(200, 'Main2 renamed', 'hash', $token, ['scope1', 's2']));
        $this->assertTrue($result);

        $chars = $main1->getPlayer()->getCharacters();
        $this->assertSame(2, count($chars));

        $this->assertSame($main2, $chars[1]);
        $this->assertSame('Main2 renamed', $chars[1]->getName());
        $this->assertSame('hash', $chars[1]->getCharacterOwnerHash());
        $this->assertSame('tk', $chars[1]->getAccessToken());
        $this->assertSame('scope1 s2', $chars[1]->getScopes());
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
        $this->assertSame('moved', $removedChar->getAction());
        $this->assertNotNull($newPlayerId);
    }

    public function testAddAltLoggedInChar()
    {
        $_SESSION['character_id'] = 100;
        $main = $this->helper->addCharacterMain('Main1', 100, [Role::USER]);

        $token = new AccessToken(['access_token' => 'tk']);
        $result = $this->service->addAlt(new EveAuthentication(100, 'Main1 renamed', 'hash', $token, []));
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
