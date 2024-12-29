<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use Eve\Sso\EveAuthentication;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Neucore\Entity\Corporation;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\Plugin;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\EsiTokenRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Repository\RemovedCharacterRepository;
use Neucore\Service\SessionData;
use Neucore\Service\UserAuth;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class UserAuthTest extends TestCase
{
    private static EntityManagerInterface $em;

    private static WriteErrorListener $writeErrorListener;

    private Helper $helper;

    private ObjectManager $om;

    private Logger $log;

    private UserAuth $service;

    private RemovedCharacterRepository $removedCharRepo;

    private CharacterRepository $characterRepo;

    private PlayerRepository $playerRepo;

    private EsiTokenRepository $esiTokenRepo;

    private Client $client;

    public static function setupBeforeClass(): void
    {
        self::$em = (new Helper())->getEm();
        self::$writeErrorListener = new WriteErrorListener();
    }

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();

        $this->helper->resetSessionData();
        $_SESSION = []; // "start" session for SessionData object and reset data

        $this->log = new Logger();
        $this->om = $this->helper->getObjectManager();
        $repoFactory = new RepositoryFactory($this->om);
        $this->client = new Client();
        $this->service = $this->helper->getUserAuthService($this->log, $this->client, null);

        $this->removedCharRepo = $repoFactory->getRemovedCharacterRepository();
        $this->characterRepo = $repoFactory->getCharacterRepository();
        $this->playerRepo = $repoFactory->getPLayerRepository();
        $this->esiTokenRepo = $repoFactory->getEsiTokenRepository();
    }

    public function tearDown(): void
    {
        self::$em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
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

    public function testLoginCharacter()
    {
        SessionData::setReadOnly(false);
        $char = $this->helper->addCharacterMain('User 756', 756);

        $this->service->loginCharacter($char);

        $user = $this->service->getUser();
        $this->assertSame(756, $_SESSION['character_id']);
        $this->assertSame(756, $user->getId());
    }

    public function testLogin_Authenticate_NoUserRoleError()
    {
        $token = new AccessToken(['access_token' => 'token']);
        $result = $this->service->login(new EveAuthentication(888, 'New User', 'char-owner-hash', $token));

        $this->assertSame(UserAuth::LOGIN_AUTHENTICATED_FAIL, $result);
        $this->assertSame(
            'UserAuth::authenticate(): Role "' . Role::USER . '" not found.',
            $this->log->getMessages()[0],
        );
    }

    /**
     * @throws \Exception
     */
    public function testLogin_Authenticate_NewUser()
    {
        $this->helper->getEm()->persist((new EveLogin())->setName(EveLogin::NAME_DEFAULT));
        $this->helper->addRoles([Role::USER]);
        SessionData::setReadOnly(false);

        $this->assertFalse(isset($_SESSION['character_id']));

        $this->client->setResponse(
            new Response(200, [], '{"name": "New User", "corporation_id": 102}'), // getCharactersCharacterId
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}'), // getCorporationsCorporationId()
        );

        $accessToken = Helper::generateToken()[0];
        $token = new AccessToken(
            ['access_token' => $accessToken, 'expires' => 1525456785, 'refresh_token' => 'refresh'],
        );
        $result = $this->service->login(new EveAuthentication(888, 'New User', 'coh', $token));

        $this->om->clear();

        $user = $this->service->getUser();
        $this->assertSame(UserAuth::LOGIN_AUTHENTICATED_SUCCESS, $result);
        $this->assertSame('New User', $user->getName());
        $this->assertSame(888, $user->getId());
        $this->assertTrue($user->getMain());
        $this->assertSame('coh', $user->getCharacterOwnerHash());
        $this->assertSame($accessToken, $user->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());
        $this->assertSame(1525456785, $user->getEsiToken(EveLogin::NAME_DEFAULT)->getExpires());
        $this->assertSame('refresh', $user->getEsiToken(EveLogin::NAME_DEFAULT)->getRefreshToken());
        $this->assertTrue($user->getEsiToken(EveLogin::NAME_DEFAULT)->getValidToken());
        $this->assertSame($_SESSION['character_id'], $user->getId());
        $this->assertSame([Role::USER], $this->service->getRoles());
        $this->assertSame('UTC', $user->getLastLogin()->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), $user->getLastLogin()->getTimestamp(), 10);
    }

    /**
     * @throws \Exception
     */
    public function testLogin_Authenticate_ExistingUser()
    {
        SessionData::setReadOnly(false);
        $corp = (new Corporation())->setId(101);
        $this->om->persist($corp);
        $char = $this->helper->addCharacterMain('Test User', 9013, [Role::USER, Role::GROUP_MANAGER]);
        $char->setCorporation($corp);
        $player = $char->getPlayer();

        $this->assertSame('123', $char->getCharacterOwnerHash());
        $this->assertSame('at', $char->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());
        $this->assertSame(123456, $char->getEsiToken(EveLogin::NAME_DEFAULT)->getExpires());
        $this->assertSame('rt', $char->getEsiToken(EveLogin::NAME_DEFAULT)->getRefreshToken());
        $this->assertNull($char->getEsiToken(EveLogin::NAME_DEFAULT)->getValidToken());
        $this->assertNull($char->getLastLogin());

        $accessToken = Helper::generateToken()[0];
        $token = new AccessToken(
            ['access_token' => $accessToken, 'expires' => 1525456785, 'refresh_token' => 'refresh'],
        );
        $result = $this->service->login(new EveAuthentication(9013, 'Test User Changed Name', '123', $token));

        $user = $this->service->getUser();
        $this->assertSame(UserAuth::LOGIN_AUTHENTICATED_SUCCESS, $result);
        $this->assertSame(9013, $_SESSION['character_id']);
        $this->assertSame(9013, $user->getId());
        $this->assertSame('Test User', $user->getName()); // name is *not* updated here
        $this->assertSame('123', $user->getCharacterOwnerHash());
        $this->assertSame($accessToken, $user->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());
        $this->assertSame(1525456785, $user->getEsiToken(EveLogin::NAME_DEFAULT)->getExpires());
        $this->assertSame('refresh', $user->getEsiToken(EveLogin::NAME_DEFAULT)->getRefreshToken());
        $this->assertTrue($char->getEsiToken(EveLogin::NAME_DEFAULT)->getValidToken());
        $this->assertSame('UTC', $user->getLastLogin()->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), $user->getLastLogin()->getTimestamp(), 10);
        $this->assertSame($user->getPlayer()->getId(), $player->getId());
    }

    public function testLogin_Authenticate_ExistingNoAltLogin()
    {
        SessionData::setReadOnly(false);
        $setting = (new SystemVariable(SystemVariable::DISABLE_ALT_LOGIN))->setValue('1');
        $this->om->persist($setting);
        $main = $this->helper->addCharacterMain('Main character', 9013, [Role::USER]);
        $alt = $this->helper->addCharacterToPlayer('Alt character', 9014, $main->getPlayer());

        $token = new AccessToken(['access_token' => 'ac', 'expires' => 1525456785, 'refresh_token' => 'rt']);
        $result = $this->service->login(
            new EveAuthentication(9014, 'Alt character', (string) $alt->getCharacterOwnerHash(), $token),
        );

        $this->assertSame(UserAuth::LOGIN_ALT_FAILED, $result);
        $this->assertSame('Login with alt 9014 denied.', $this->log->getMessages()[0]);
    }

    public function testLogin_Authenticate_NewOwner()
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
        $result = $this->service->login(new EveAuthentication(9014, 'Test User2', '789', $token));

        $user = $this->service->getUser();
        $newPlayer = $user->getPlayer();

        $this->assertSame(UserAuth::LOGIN_AUTHENTICATED_SUCCESS, $result);
        $this->assertSame(9014, $_SESSION['character_id']);
        $this->assertSame(9014, $user->getId());
        $this->assertSame('789', $user->getCharacterOwnerHash());
        $this->assertNotSame($newPlayer->getId(), $player->getId());

        // check RemovedCharacter
        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 9014]);
        $this->assertSame($user->getId(), $removedChar->getcharacterId());
        $this->assertSame($player->getId(), $removedChar->getPlayer()->getId());
        $this->assertSame($newPlayer->getId(), $removedChar->getNewPlayer()->getId());
        $this->assertSame(RemovedCharacter::REASON_MOVED_OWNER_CHANGED, $removedChar->getReason());
    }

    public function testLogin_addAltMoveOrMergeAccounts_NoRefreshToken()
    {
        $_SESSION['character_id'] = 100;
        $main = $this->helper->addCharacterMain('Main', 100, [Role::USER]);
        $player = $main->getPlayer();

        $this->assertSame(1, count($player->getCharacters()));

        $this->client->setResponse(
            new Response(200, [], '{"name": "Alt 1", "corporation_id": 102}'), // getCharactersCharacterId
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}'), // getCorporationsCorporationId()
        );

        $token = new AccessToken(['access_token' => 'tk', 'expires' => 1525456785]);
        $result = $this->service->login(new EveAuthentication(101, 'Alt 1', 'hash', $token));
        $this->assertSame(UserAuth::LOGIN_CHARACTER_ADDED_SUCCESS, $result);

        $chars = $player->getCharacters();
        $this->assertSame(2, count($chars));

        $this->assertSame(101, $chars[1]->getId());
        $this->assertLessThanOrEqual(time(), $chars[1]->getCreated()->getTimestamp());
        $this->assertSame('Alt 1', $chars[1]->getName());
        $this->assertSame('hash', $chars[1]->getCharacterOwnerHash());
        $this->assertNull($chars[1]->getEsiToken(EveLogin::NAME_DEFAULT));
        $this->assertFalse($chars[1]->getMain());
    }

    public function testLogin_addAltMoveOrMergeAccounts_NewCharAddsGroups()
    {
        $_SESSION['character_id'] = 100;
        $this->helper->addRoles([Role::GROUP_MANAGER, Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER]);
        $group = (new Group())->setName('g1');
        $corp = (new Corporation())->setId(102)->setName('c1')->setTicker('t1')->addGroup($group);
        $this->om->persist($group);
        $this->om->persist($corp);
        $main = $this->helper->addCharacterMain('Main', 100, [Role::USER]);

        $player = $main->getPlayer();
        $this->client->setResponse(
            new Response(200, [], '{"name": "Alt 1", "corporation_id": 102}'), // getCharactersCharacterId
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "c1 updated", "ticker": "t1"}'), // getCorporationsCorporationId()
        );
        $token = new AccessToken(['access_token' => 'tk', 'expires' => 1525456785, 'refresh_token' => 'rf']);

        $result = $this->service->login(new EveAuthentication(101, 'Alt 1', 'hash', $token));

        $this->assertSame(UserAuth::LOGIN_CHARACTER_ADDED_SUCCESS, $result);
        $this->assertSame(0, count($this->log->getMessages()));

        $chars = $player->getCharacters();
        $this->assertSame(2, count($chars));
        $this->assertSame([$group->getId()], $chars[0]->getPlayer()->getGroupIds());
        $this->assertSame('c1 updated', $chars[1]->getCorporation()->getName());
        $this->assertSame(101, $chars[1]->getId());
        $this->assertLessThanOrEqual(time(), $chars[1]->getCreated()->getTimestamp());
        $this->assertSame('Alt 1', $chars[1]->getName());
        $this->assertSame('hash', $chars[1]->getCharacterOwnerHash());
        $this->assertSame('rf', $chars[1]->getEsiToken(EveLogin::NAME_DEFAULT)->getRefreshToken());
        $this->assertFalse($chars[1]->getMain());
    }

    /**
     * @throws \Exception
     */
    public function testLogin_addAltMoveOrMergeAccounts_ExistingCharMerge()
    {
        $_SESSION['character_id'] = 100;
        $corp = (new Corporation())->setId(101);
        $this->om->persist($corp);
        $main1 = $this->helper->addCharacterMain('Main1', 100, [Role::USER]);
        $main2 = $this->helper->addCharacterMain('Main2', 200, [Role::USER]);
        $this->helper->addCharacterToPlayer('Alt2', 201, $main2->getPlayer());
        $main2->setCorporation($corp);
        $newPlayerId = $main1->getPlayer()->getId();
        $oldPlayerId = $main2->getPlayer()->getId();

        $accessToken = Helper::generateToken()[0];
        $token = new AccessToken(['access_token' => $accessToken, 'expires' => 1525456785, 'refresh_token' => 'rf']);

        // Login with same owner hash
        $result = $this->service->login(new EveAuthentication(200, 'Main2 renamed', '123', $token));
        $this->om->clear();

        $this->assertSame(UserAuth::LOGIN_ACCOUNTS_MERGED, $result);
        $main1Loaded = $this->characterRepo->find(100);
        $main2Loaded = $this->characterRepo->find(200);
        $player2 = $this->playerRepo->find($oldPlayerId);

        $chars = $main1Loaded->getPlayer()->getCharacters();
        $this->assertSame(3, count($chars));
        $this->assertSame(0, count($player2->getCharacters()));

        $this->assertSame($main2Loaded->getId(), $chars[2]->getId());
        $this->assertFalse($chars[0]->getMain());
        $this->assertTrue($chars[1]->getMain());
        $this->assertFalse($chars[2]->getMain());
        $this->assertSame('Main1', $main1Loaded->getPlayer()->getName());
        $this->assertSame('Main1', $main2Loaded->getPlayer()->getName());
        $this->assertSame('Alt2', $chars[0]->getName());
        $this->assertSame('Main1', $chars[1]->getName());
        $this->assertSame('Main2', $chars[2]->getName()); // name is *not* updated here
        $this->assertSame('123', $chars[2]->getCharacterOwnerHash());
        $this->assertSame($accessToken, $chars[2]->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());
        $this->assertSame(1525456785, $chars[2]->getEsiToken(EveLogin::NAME_DEFAULT)->getExpires());
        $this->assertSame('rf', $chars[2]->getEsiToken(EveLogin::NAME_DEFAULT)->getRefreshToken());
        $this->assertTrue($chars[2]->getEsiToken(EveLogin::NAME_DEFAULT)->getValidToken());

        // check RemovedCharacter
        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 200]);
        $this->assertSame($main2Loaded->getId(), $removedChar->getcharacterId());
        $this->assertNotSame($main2Loaded->getPlayer()->getId(), $removedChar->getPlayer()->getId());
        $this->assertGreaterThan(0, $main2Loaded->getPlayer()->getId());
        $this->assertSame($oldPlayerId, $removedChar->getPlayer()->getId());
        $this->assertGreaterThan(0, $oldPlayerId);
        $this->assertSame($newPlayerId, $removedChar->getNewPlayer()->getId());
        $this->assertSame(RemovedCharacter::REASON_MOVED, $removedChar->getReason());
        $this->assertGreaterThan(0, $newPlayerId);
    }

    /**
     * @throws \Exception
     */
    public function testLogin_addAltMoveOrMergeAccounts_ExistingCharMove()
    {
        $_SESSION['character_id'] = 100;
        $corp = (new Corporation())->setId(101);
        $this->om->persist($corp);
        $main1 = $this->helper->addCharacterMain('Main1', 100, [Role::USER]);
        $main2 = $this->helper->addCharacterMain('Main2', 200, [Role::USER]);
        $this->helper->addCharacterToPlayer('Alt2', 201, $main2->getPlayer());
        $main2->setCorporation($corp);
        $newPlayerId = $main1->getPlayer()->getId();
        $oldPlayerId = $main2->getPlayer()->getId();

        $accessToken = Helper::generateToken()[0];
        $token = new AccessToken(['access_token' => $accessToken, 'expires' => 1525456785, 'refresh_token' => 'rf']);

        // Login with different owner hash
        $result = $this->service->login(new EveAuthentication(200, 'Main2 renamed', '456', $token));
        $this->om->clear();

        $this->assertSame(UserAuth::LOGIN_CHARACTER_ADDED_SUCCESS, $result);
        $main1Loaded = $this->characterRepo->find(100);
        $main2Loaded = $this->characterRepo->find(200);
        $player2 = $this->playerRepo->find($oldPlayerId);

        $chars = $main1Loaded->getPlayer()->getCharacters();
        $this->assertSame(2, count($chars));
        $this->assertSame(1, count($player2->getCharacters()));
        $alt2Loaded = $player2->getCharacters()[0];

        $this->assertSame($main2Loaded->getId(), $chars[1]->getId());
        $this->assertTrue($chars[0]->getMain());
        $this->assertFalse($chars[1]->getMain());
        $this->assertTrue($alt2Loaded->getMain()); // main was moved, this is the only char on that account now
        $this->assertSame('Main1', $main1Loaded->getPlayer()->getName());
        $this->assertSame('Main1', $main2Loaded->getPlayer()->getName());
        $this->assertSame('Alt2', $alt2Loaded->getName());
        $this->assertSame('Main1', $chars[0]->getName());
        $this->assertSame('Main2', $chars[1]->getName()); // name is *not* updated here
        $this->assertSame('456', $chars[1]->getCharacterOwnerHash());
        $this->assertSame($accessToken, $chars[1]->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());
        $this->assertSame(1525456785, $chars[1]->getEsiToken(EveLogin::NAME_DEFAULT)->getExpires());
        $this->assertSame('rf', $chars[1]->getEsiToken(EveLogin::NAME_DEFAULT)->getRefreshToken());
        $this->assertTrue($chars[1]->getEsiToken(EveLogin::NAME_DEFAULT)->getValidToken());

        // check RemovedCharacter
        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 200]);
        $this->assertSame($main2Loaded->getId(), $removedChar->getcharacterId());
        $this->assertNotSame($main2Loaded->getPlayer()->getId(), $removedChar->getPlayer()->getId());
        $this->assertGreaterThan(0, $main2Loaded->getPlayer()->getId());
        $this->assertSame($oldPlayerId, $removedChar->getPlayer()->getId());
        $this->assertGreaterThan(0, $oldPlayerId);
        $this->assertSame($newPlayerId, $removedChar->getNewPlayer()->getId());
        $this->assertSame(RemovedCharacter::REASON_MOVED_OWNER_CHANGED, $removedChar->getReason());
        $this->assertGreaterThan(0, $newPlayerId);
    }

    public function testLogin_addAltMoveOrMergeAccounts_LoggedInChar()
    {
        $_SESSION['character_id'] = 100;
        $main = $this->helper->addCharacterMain('Main1', 100, [Role::USER]);
        $corp = (new Corporation())->setId(101);
        $this->om->persist($corp);
        $main->setCorporation($corp);

        $token = new AccessToken(['access_token' => 'tk']);
        $result = $this->service->login(new EveAuthentication(100, 'Main1 renamed', 'hash', $token));
        $this->assertSame(UserAuth::LOGIN_CHARACTER_ADDED_SUCCESS, $result);

        $chars = $main->getPlayer()->getCharacters();
        $this->assertSame(1, count($chars));
        $this->assertSame('Main1', $chars[0]->getName()); // name changed but is *not* updated here
        $this->assertTrue($chars[0]->getMain());
    }

    public function testLogin_addAltMoveOrMergeAccounts_NotAuthenticated()
    {
        $_SESSION['character_id'] = 100;
        $this->helper->addCharacterMain('Main1', 100, [Role::USER]);
        $token = new AccessToken(['access_token' => 'tk']);

        self::$em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);
        $result = $this->service->login(new EveAuthentication(101, 'Alt', 'hash', $token, []));

        $this->assertSame(UserAuth::LOGIN_CHARACTER_ADDED_FAIL, $result);
    }

    public function testFindCharacterOnAccount_NotLoggedIn()
    {
        $result = $this->service->findCharacterOnAccount(
            new EveAuthentication(100, 'Main1', 'hash', new AccessToken(['access_token' => 'tk'])),
        );
        $this->assertNull($result);
    }

    public function testFindCharacterOnAccount_CharacterNotFound()
    {
        $_SESSION['character_id'] = 100;
        $this->helper->addCharacterMain('Main1', 100, [Role::USER]);

        $result = $this->service->findCharacterOnAccount(
            new EveAuthentication(200, 'Main1', 'hash', new AccessToken(['access_token' => 'tk'])),
        );

        $this->assertNull($result);
    }

    public function testFindCharacterOnAccount_Success()
    {
        $_SESSION['character_id'] = 100;
        $this->helper->addCharacterMain('Main1', 100, [Role::USER]);

        $result = $this->service->findCharacterOnAccount(
            new EveAuthentication(100, 'Main1', 'hash', new AccessToken(['access_token' => 'a',])),
        );

        $this->assertSame(100, $result->getId());
    }

    public function testAddToken_SaveFailed()
    {
        $_SESSION['character_id'] = 100;
        $character = $this->helper->addCharacterMain('Main1', 100, [Role::USER]);

        $result = $this->service->addToken(
            new EveLogin(),
            new EveAuthentication(100, 'Main1', 'hash', new AccessToken(['access_token' => 'a-second-token'])),
            $character,
        );

        $this->assertFalse($result);
        $this->assertSame(1, count($this->log->getHandler()->getRecords()));
        $this->assertStringStartsWith(
            'A new entity was found', // EveLogin was not persisted
            $this->log->getMessages()[0],
        );
    }

    public function testAddToken_Success()
    {
        $eveLogin = (new EveLogin())->setName('custom1')->setEveRoles(['Diplomat']);
        $this->helper->getEm()->persist($eveLogin);
        $_SESSION['character_id'] = 100;
        $character = $this->helper->addCharacterMain('Main1', 100, [Role::USER]);

        $result = $this->service->addToken($eveLogin, new EveAuthentication(100, 'Main1', 'hash', new AccessToken([
            'access_token' => 'a-second-token',
            'refresh_token' => 'ref.t.',
            'expires' => 1525456785,
        ])), $character);

        $this->assertTrue($result);
        $tokens = $this->esiTokenRepo->findBy([]);
        $this->assertSame(2, count($tokens));
        $this->assertSame('a-second-token', $tokens[1]->getAccessToken());
        $this->assertSame('ref.t.', $tokens[1]->getRefreshToken());
        $this->assertSame(1525456785, $tokens[1]->getExpires());
        $this->assertTrue($tokens[1]->getValidToken());
        $this->assertLessThanOrEqual(time(), $tokens[1]->getValidTokenTime()->getTimestamp());
        $this->assertLessThanOrEqual(time(), $tokens[1]->getLastChecked()->getTimestamp());
        $this->assertTrue($tokens[1]->getHasRoles());
    }

    public function testHasRequiredGroups()
    {
        $this->helper->emptyDb();
        $group = (new Group())->setName('G1');
        $this->helper->getEm()->persist($group);
        $this->helper->getEm()->flush();

        // no required group, no logged-in user
        $service = new Plugin();
        $this->assertFalse($this->service->hasRequiredGroups($service));

        // no required group, no logged-in user, allow anonymous
        $service = new Plugin();
        $this->assertTrue($this->service->hasRequiredGroups($service, true));

        // log in user
        $character = $this->helper->addCharacterMain('Test User', 800);
        $_SESSION['character_id'] = 800;
        $this->assertTrue($this->service->hasRequiredGroups($service));

        // add require group
        $conf = new PluginConfigurationDatabase();
        $conf->requiredGroups = [$group->getId()];
        $service->setConfigurationDatabase($conf);
        $this->assertFalse($this->service->hasRequiredGroups($service));

        // add group to player
        $character->getPlayer()->addGroup($group);
        $this->assertTrue($this->service->hasRequiredGroups($service));

        // add another require group
        $conf->requiredGroups[] = 2;
        $service->setConfigurationDatabase($conf);
        $this->assertTrue($this->service->hasRequiredGroups($service));

        // "deactivate" account
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $corporation = (new Corporation())->setId(101);
        $character->setCorporation($corporation)->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(false);
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->persist($corporation);
        $this->helper->getEm()->flush();
        $this->assertFalse($this->service->hasRequiredGroups($service));

        // set deactivation delay
        $setting4 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY))->setValue('60');
        $this->helper->getEm()->persist($setting4);
        $this->helper->getEm()->flush();
        $this->assertTrue($this->service->hasRequiredGroups($service));
    }
}
