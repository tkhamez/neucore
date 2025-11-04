<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Service;

require_once __DIR__ . '/Account/plugin/src/TestService.php';

use Doctrine\Persistence\ObjectManager;
use Eve\Sso\EveAuthentication;
use Eve\Sso\JsonWebToken;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Handler\TestHandler;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Entity\Alliance;
use Neucore\Entity\App;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\Plugin;
use Neucore\Entity\Watchlist;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterNameChangeRepository;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\PlayerLoginsRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Repository\RemovedCharacterRepository;
use Neucore\Service\Account;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\Logger;
use Tests\Unit\Service\Account\TestService;

class AccountTest extends TestCase
{
    private Helper $helper;

    private ObjectManager $om;

    private Logger $log;

    private Client $client;

    private Account $service;

    private CharacterRepository $charRepo;

    private PlayerRepository $playerRepo;

    private RemovedCharacterRepository $removedCharRepo;

    private PlayerLoginsRepository $playerLoginsRepo;

    private CharacterNameChangeRepository $characterNameChangeRepo;

    private Player $player1;

    private Player $player2;

    private Corporation $corp1;

    private Corporation $corp2;

    private Watchlist $watchlist1;

    private Watchlist $watchlist2;

    private Group $group1;

    private Group $group2;

    /**
     * group-manager
     */
    private Role $role0;

    /**
     * tracking
     */
    private Role $role1;

    /**
     * watchlist
     */
    private Role $role2;

    /**
     * app-manager
     */
    private Role $role3;

    /**
     * user-chars
     */
    private Role $role4;

    /**
     * esi
     */
    private Role $role5;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        list($this->role0, $this->role1, $this->role2, $this->role3, $this->role4, $this->role5) =
            $this->helper->addRoles([
                Role::GROUP_MANAGER, Role::TRACKING, Role::WATCHLIST, Role::APP_MANAGER, Role::USER_CHARS,
                Role::ESI, Role::USER_ADMIN, Role::WATCHLIST_MANAGER,
            ]);
        $this->om = $this->helper->getObjectManager();

        $this->log = new Logger();
        $this->log->pushHandler(new TestHandler());

        $this->client = new Client();
        $repoFactory = new RepositoryFactory($this->om);

        $config = new Config([
            'eve' => Helper::getEveConfig(),
            'plugins_install_dir' => __DIR__ . '/Account',
        ]);
        $this->service = $this->helper->getAccountService($this->log, $this->client, $config);
        $this->charRepo = $repoFactory->getCharacterRepository();
        $this->playerRepo = $repoFactory->getPlayerRepository();
        $this->removedCharRepo = $repoFactory->getRemovedCharacterRepository();
        $this->playerLoginsRepo = $repoFactory->getPlayerLoginsRepository();
        $this->characterNameChangeRepo = $repoFactory->getCharacterNameChangeRepository();

        TestService::$updateAccount = [];
    }

    public function testCreateNewPlayerWithMain(): void
    {
        $character = $this->service->createNewPlayerWithMain(234, 'bcd');

        $this->assertTrue($character->getMain());
        $this->assertSame(234, $character->getId());
        $this->assertSame('bcd', $character->getName());
        $this->assertSame('bcd', $character->getPlayer()->getName());
        $this->assertSame([], $character->getPlayer()->getRoles());
        $this->assertLessThanOrEqual(time(), $character->getCreated()->getTimestamp());
    }

    public function testMoveCharacterToNewPlayer(): void
    {
        // this also updates groups now
        (new Helper())->emptyDb();

        $char = new Character();
        $char->setId(100);
        $char->setName('char name');
        $player = new Player();
        $player->setId(5);
        $player->setName($char->getName());
        $player->addCharacter($char);
        $char->setPlayer($player);

        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->flush();

        $character = $this->service->moveCharacterToNewAccount($char);

        $this->assertSame(0, count($this->log->getHandler()->getRecords()));
        $newPlayer = $character->getPlayer();

        $this->assertSame($char, $character);
        $this->assertNotSame($player, $newPlayer);
        $this->assertSame('char name', $newPlayer->getName());

        $this->assertSame(100, $player->getRemovedCharacters()[0]->getCharacterId());
        $this->assertSame($newPlayer, $player->getRemovedCharacters()[0]->getNewPlayer());
        $this->assertSame(
            RemovedCharacter::REASON_MOVED_OWNER_CHANGED,
            $player->getRemovedCharacters()[0]->getReason(),
        );
        $this->assertSame($newPlayer, $player->getRemovedCharacters()[0]->getNewPlayer());

        // test relation after persist
        $this->om->flush();
        $this->om->clear();
        $newPlayerLoaded = $this->playerRepo->find($newPlayer->getId());
        $this->assertSame(100, $newPlayerLoaded->getIncomingCharacters()[0]->getCharacterId());
    }

    public function testMoveCharacter(): void
    {
        (new Helper())->emptyDb();

        $char = new Character();
        $char->setId(100);
        $char->setName('char name');
        $player = new Player();
        $player->setId(5);
        $player->setName($char->getName());
        $player->addCharacter($char);
        $char->setPlayer($player);

        $newPlayer = new Player();
        $newPlayer->setName('old name');

        $this->om->persist($player);
        $this->om->persist($newPlayer);
        $this->om->persist($char);
        $this->om->flush();

        $this->service->moveCharacter($char, $newPlayer, RemovedCharacter::REASON_MOVED_OWNER_CHANGED);

        $newPlayer = $char->getPlayer();
        $this->assertSame('old name', $newPlayer->getName()); // not changed

        $this->assertSame(100, $player->getRemovedCharacters()[0]->getCharacterId());
        $this->assertSame($newPlayer, $player->getRemovedCharacters()[0]->getNewPlayer());
        $this->assertSame(
            RemovedCharacter::REASON_MOVED_OWNER_CHANGED,
            $player->getRemovedCharacters()[0]->getReason(),
        );
        $this->assertSame($newPlayer, $player->getRemovedCharacters()[0]->getNewPlayer());

        // test relation after persist
        $this->om->flush();
        $this->om->clear();
        $newPlayerLoaded = $this->playerRepo->find($newPlayer->getId());
        $this->assertSame(100, $newPlayerLoaded->getIncomingCharacters()[0]->getCharacterId());
    }

    public function testUpdateAndStoreCharacterWithPlayer_NoEveLogin(): void
    {
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            new Character(),
            new EveAuthentication(100, '', '', new AccessToken(['access_token' => 'irrelevant'])),
        );
        $this->assertFalse($result);

        $this->assertSame(
            'Account::updateAndStoreCharacterWithPlayer: Could not find default EveLogin entity.',
            $this->log->getHandler()->getRecords()[0]['message'],
        );
    }

    /**
     * @throws \Exception
     */
    public function testUpdateAndStoreCharacterWithPlayer_Success(): void
    {
        $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
        $this->om->persist($eveLogin);
        $this->om->flush();

        $player = (new Player())->setName('name');
        $char = (new Character())->setName('char name')->setId(12)->setMain(true);
        $char->setPlayer($player);
        $player->addCharacter($char);

        $this->client->setResponse(
            new Response(200, [], '{"name": "char name changed", "corporation_id": 102}'), // getCharactersCharacterId
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}'), // getCorporationsCorporationId()
        );

        $expires = time() + (60 * 20);
        $token = Helper::generateToken(['s1', 's2']);
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                100,
                'will be updated because corporation is missing',
                'character-owner-hash',
                new AccessToken(['access_token' => $token[0], 'refresh_token' => 'r-t', 'expires' => $expires]),
            ),
        );
        $this->assertTrue($result);

        $this->om->clear();

        $character = $this->charRepo->find(12);
        $esiToken = $character->getEsiToken(EveLogin::NAME_DEFAULT);

        $this->assertSame('char name changed', $character->getName());
        $this->assertTrue($character->getMain());
        $this->assertSame('char name changed', $player->getName());
        $this->assertNull($character->getCharacterOwnerHash()); // no longer set in this method
        $this->assertSame($token[0], $esiToken->getAccessToken());
        $this->assertSame('r-t', $esiToken->getRefreshToken());
        $this->assertSame($expires, $esiToken->getExpires());
        $this->assertLessThanOrEqual(time(), $esiToken->getLastChecked()->getTimestamp());
        $this->assertTrue($character->getEsiToken(EveLogin::NAME_DEFAULT)->getValidToken());
        $this->assertSame(['s1', 's2'], (new JsonWebToken(new AccessToken([
            'access_token' => $esiToken->getAccessToken(),
            'refresh_token' => $esiToken->getRefreshToken(),
            'expires' => $esiToken->getExpires(),
        ])))->getEveAuthentication()->getScopes());
        $this->assertSame(102, $character->getCorporation()->getId());
        $this->assertSame('name corp', $character->getCorporation()->getName());
    }

    /**
     * @throws \Exception
     */
    public function testUpdateAndStoreCharacterWithPlayer_DoesNotReplaceValidTokenWithNoScopesToken(): void
    {
        $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
        // Test against valid token (i.e. it would have scopes)
        $existingToken = (new EsiToken())->setEveLogin($eveLogin)->setRefreshToken('rt')
            ->setAccessToken('at-existing-token')->setExpires(123)->setValidToken(true);
        $corp = (new Corporation())->setId(1);
        $player = (new Player())->setName('p-name');
        $char = (new Character())->setName('c-name')->setId(12)->setPlayer($player)->setCorporation($corp);
        $char->addEsiToken($existingToken);
        $existingToken->setCharacter($char);
        $this->om->persist($eveLogin);
        $this->om->persist($existingToken);
        $this->om->persist($corp);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->flush();

        // Pass in a new token with no scopes
        $scopes = [];
        $token = Helper::generateToken($scopes);
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                100,
                '',
                '',
                new AccessToken([
                    'access_token' => $token[0],
                    'refresh_token' => 'rt',
                    'expires' => time() + 1000,
                ]),
                $scopes,
            ),
        );
        $this->assertTrue($result);
        $this->om->clear();

        // We expect the token to still be the initial valid token, not the no-scopes token
        $updatedCharacter = $this->charRepo->find(12);
        $this->assertSame(
            'at-existing-token',
            $updatedCharacter?->getEsiToken(EveLogin::NAME_DEFAULT)?->getAccessToken(),
        );
    }

    public function testUpdateAndStoreCharacterWithPlayer_NoToken(): void
    {
        $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
        $this->om->persist($eveLogin);
        $this->om->flush();

        $corp = (new Corporation())->setId(1);
        $this->om->persist($corp);
        $player = (new Player())->setName('p-name');
        $char = (new Character())->setName('c-name')->setId(12)->setPlayer($player)->setCorporation($corp);

        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                100,
                'char name changed',
                'character-owner-hash',
                new AccessToken(['access_token' => 'a-t']),
            ),
        );
        $this->assertTrue($result);

        $this->om->clear();

        $character = $this->charRepo->find(12);
        $this->assertSame('c-name', $character->getName()); // the name is *not* updated here
        $this->assertNull($character->getEsiToken(EveLogin::NAME_DEFAULT));
        $this->assertSame(0, count($character->getCharacterNameChanges()));
    }

    public function testUpdateAndStoreCharacterWithPlayer_NoToken_RemovesExistingToken(): void
    {
        $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
        $defaultEsiToken = (new EsiToken())->setEveLogin($eveLogin)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(123);
        $corp = (new Corporation())->setId(1);
        $player = (new Player())->setName('p-name');
        $char = (new Character())->setName('c-name')->setId(12)->setPlayer($player)->setCorporation($corp);
        $defaultEsiToken->setCharacter($char);
        $this->om->persist($eveLogin);
        $this->om->persist($defaultEsiToken);
        $this->om->persist($corp);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->flush();
        $this->om->clear();

        $char = $this->charRepo->find(12);
        $char = $char ?: new Character(); // only for PHPStan
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(100, '', '', new AccessToken(['access_token' => 'a-t'])),
        );
        $this->assertTrue($result);

        $this->om->clear();

        $character = $this->charRepo->find(12);
        $this->assertNull($character->getEsiToken(EveLogin::NAME_DEFAULT));
    }

    public function testIncreaseLoginCount(): void
    {
        $player = (new PLayer())->setName('p');
        $this->om->persist($player);

        $this->service->increaseLoginCount($player);
        $this->service->increaseLoginCount($player);

        $logins = $this->playerLoginsRepo->findBy([]);

        $this->assertSame(1, count($logins));
        $this->assertGreaterThanOrEqual(1, $logins[0]->getPlayer()->getId());
        $this->assertSame($player->getId(), $logins[0]->getPlayer()->getId());
        $this->assertSame(2, $logins[0]->getCount());
        $this->assertSame((int) date('Y'), $logins[0]->getYear());
        $this->assertSame((int) date('m'), $logins[0]->getMonth());
    }

    public function testCheckCharacter_DeletesBiomassedChar(): void
    {
        $corp = (new Corporation())->setId(EsiData::CORPORATION_DOOMHEIM_ID);
        $player = (new Player())->setName('p');
        $char = (new Character())->setId(31)->setName('n31')->setCorporation($corp)->setPlayer($player);
        $this->om->persist($corp);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->flush();

        $result = $this->service->checkCharacter($char);
        $this->assertSame(Account::CHECK_CHAR_DELETED, $result);

        $this->om->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
        $this->assertSame(RemovedCharacter::REASON_DELETED_BIOMASSED, $removedChar->getReason());
    }

    public function testCheckCharacter_NoToken(): void
    {
        $char = (new Character())->setId(100)->setName('name');
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char);
        $this->assertSame(Account::CHECK_TOKEN_NA, $result);

        $this->om->clear();
        $charLoaded = $this->charRepo->find(100);
        $this->assertNull($charLoaded->getEsiToken(EveLogin::NAME_DEFAULT));
    }

    public function testCheckCharacter_InvalidToken(): void
    {
        $expires = time() - 1000;
        $char = $this->setUpCharacterWithToken($expires, true);

        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(400, [], '{"error": "invalid_grant"}'),
        );

        $result = $this->service->checkCharacter($char);
        $this->assertSame(Account::CHECK_TOKEN_NOK, $result);

        $this->om->clear();
        $charLoaded = $this->charRepo->find(31);
        $this->assertEmpty($charLoaded->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken());
        $this->assertNotEmpty($charLoaded->getEsiToken(EveLogin::NAME_DEFAULT)->getRefreshToken()); // not changed
        $this->assertFalse($charLoaded->getEsiToken(EveLogin::NAME_DEFAULT)->getValidToken());
    }

    public function testCheckCharacter_RequestError(): void
    {
        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(200, [], '{"access_token": "new-at", "refresh_token" => "r-t", "expires" => 1}'),
        );

        $expires = time() - 1000;
        $char = $this->setUpCharacterWithToken($expires, true);

        $result = $this->service->checkCharacter($char);
        $this->assertSame(Account::CHECK_TOKEN_PARSE_ERROR, $result);
        $this->assertTrue($char->getEsiToken(EveLogin::NAME_DEFAULT)->getValidToken()); // not changed!
    }

    /**
     * @throws \Exception
     */
    public function testCheckCharacter_ValidTokenNoScopes(): void
    {
        list($token) = Helper::generateToken([]);
        $newExpires = time() + 60;
        $this->client->setResponse(
            // for getAccessToken()
            new Response(200, [], '{
                "access_token": ' . json_encode($token) . ', 
                "refresh_token": "r-t", 
                "expires": ' . $newExpires . '
            }'),
        );

        $expires = time() - 1000;
        $char = $this->setUpCharacterWithToken($expires, true);

        $result = $this->service->checkCharacter($char);
        $this->assertSame(Account::CHECK_TOKEN_NOK, $result);

        $this->om->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character->getEsiToken(EveLogin::NAME_DEFAULT)->getValidToken());
        $this->assertSame($token, $character->getEsiToken(EveLogin::NAME_DEFAULT)->getAccessToken()); // updated
        $this->assertSame('r-t', $character->getEsiToken(EveLogin::NAME_DEFAULT)->getRefreshToken()); // updated
        $this->assertSame($newExpires, $character->getEsiToken(EveLogin::NAME_DEFAULT)->getExpires()); // updated
    }

    /**
     * @throws \Exception
     */
    public function testCheckCharacter_InvalidJwtData(): void
    {
        list($token) = Helper::generateToken(['Scope1'], 'Char Name', ''); // with empty character owner hash
        $this->client->setResponse(
            // for getAccessToken()
            new Response(200, [], '{
                "access_token": ' . json_encode($token) . ', 
                "refresh_token": "r-t", 
                "expires": ' . (time() + 60) . '
            }'),
        );
        $char = $this->setUpCharacterWithToken(time() - 60, true);

        $result = $this->service->checkCharacter($char);

        $this->assertSame(Account::CHECK_TOKEN_OK, $result);
        $this->assertSame(['Unexpected JWT data, missing character owner hash.'], $this->log->getMessages());
    }

    /**
     * @throws \Exception
     */
    public function testCheckCharacter_ValidWithScopes_UpdateOtherTokens_CheckRoles(): void
    {
        list($token) = Helper::generateToken(['scope1', 'scope2'], 'Old Name');
        $this->client->setResponse(
            // second token "custom.1" - for getAccessToken()
            new Response(200, [], '{
                "access_token": ' . json_encode($token) . ',
                "expires_in": 1200,
                "refresh_token": "fM0...gEy"
            }'),
            new Response(200, [], '{"roles": ["Accountant"]}'), // read_corporation_roles for "custom.1"
            new Response(200, [], '{"roles": []}'), // read_corporation_roles for "custom.3"

            // fifth token "custom.4" - for refreshAccessToken()
            new Response(400, [], '{"error": "invalid_grant"}'),

            // default token - for getAccessToken()
            new Response(200, [], '{
                "access_token": ' . json_encode($token) . ',
                "expires_in": 1200,
                "refresh_token": "gEy...fM0"
            }'),
        );

        $expires = time() - 1000;
        $char = $this->setUpCharacterWithToken($expires, true);
        $this->addTokenToChar('custom.1', $char, ['Accountant'], $expires); // test: setHasRoles = true
        $this->addTokenToChar('custom.2', $char, [], time() + 1000)->setHasRoles(true); // test: setHasRoles = null
        $this->addTokenToChar('custom.3', $char, ['Director'], time() + 1000); // test: setHasRoles = false
        $this->addTokenToChar('custom.4', $char, [], time() - 1000); // test: updateEsiToken fails

        $result = $this->service->checkCharacter($char);
        $this->assertSame(Account::CHECK_TOKEN_OK, $result);

        $this->om->clear();

        $character = $this->charRepo->find(31);
        $this->assertSame('n31', $character->getName());

        $defaultToken = $character->getEsiToken(EveLogin::NAME_DEFAULT);
        $this->assertTrue($defaultToken->getValidToken()); // updated
        $this->assertSame($token, $defaultToken->getAccessToken()); // updated
        $this->assertGreaterThan($expires, $defaultToken->getExpires()); // updated
        $this->assertSame('gEy...fM0', $defaultToken->getRefreshToken()); // updated

        $secondToken = $character->getEsiToken('custom.1');
        $this->assertTrue($secondToken->getValidToken()); // updated
        $this->assertTrue($secondToken->getHasRoles()); // updated
        $this->assertSame($token, $secondToken->getAccessToken()); // updated
        $this->assertGreaterThan($expires, $secondToken->getExpires()); // updated
        $this->assertSame('fM0...gEy', $secondToken->getRefreshToken()); // updated

        $thirdToken = $character->getEsiToken('custom.2');
        $this->assertNull($thirdToken->getHasRoles());

        $fourthToken = $character->getEsiToken('custom.3');
        $this->assertFalse($fourthToken->getHasRoles());

        $characterNameChange = $this->characterNameChangeRepo->findBy([]);
        $this->assertSame(0, count($characterNameChange)); // change via ESI token is no longer recorded
    }

    /**
     * @throws \Exception
     */
    public function testCheckCharacter_DeletesMovedChar(): void
    {
        list($token) = Helper::generateToken();
        $this->client->setResponse(
            new Response(200, [], '{
                "access_token": ' . json_encode($token) . ',
                "expires_in": 1200,
                "refresh_token": "gEy...fM0"
            }'), // for getAccessToken()
        );

        $expires = time() - 1000;
        $char = $this->setUpCharacterWithToken($expires, true, 'old-hash');

        $result = $this->service->checkCharacter($char);
        $this->assertSame(Account::CHECK_CHAR_DELETED, $result);

        $this->om->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
        $this->assertSame(RemovedCharacter::REASON_DELETED_OWNER_CHANGED, $removedChar->getReason());
    }

    public function testMergeAccounts_MoveCharacter(): void
    {
        $player = (new Player())->setName('player 1');
        $newPlayer = (new Player())->setName('player 2');
        $char1 = (new Character())->setId(10)->setName('char1')->setPlayer($player)->setMain(true);
        $char2 = (new Character())->setId(11)->setName('char2')->setPlayer($newPlayer)->setMain(true);
        $char3 = (new Character())->setId(12)->setName('char3')->setPlayer($newPlayer)->setMain(false);
        $player->addCharacter($char1);
        $newPlayer->addCharacter($char2);
        $newPlayer->addCharacter($char3);
        $this->om->persist($player);
        $this->om->persist($newPlayer);
        $this->om->persist($char1);
        $this->om->persist($char2);
        $this->om->persist($char3);
        $this->om->flush();

        $mergedPlayer = $this->service->mergeAccounts($player, $newPlayer);
        $this->om->clear();

        $this->assertSame([], $this->log->getMessages());
        $players = $this->playerRepo->findBy([]);
        $this->assertSame($players[0]->getId(), $mergedPlayer->getId());
        $this->assertSame('player 1', $players[0]->getName());
        $this->assertSame('player 2', $players[1]->getName());

        $this->assertSame(3, count($players[0]->getCharacters()));
        $this->assertSame(0, count($players[1]->getCharacters()));
        $this->assertSame('char2', $players[0]->getCharacters()[1]->getName());
        $this->assertTrue($players[0]->getCharacter(10)->getMain());
        $this->assertFalse($players[0]->getCharacter(11)->getMain()); // flag changed
        $removedChar1 = $players[1]->getRemovedCharacters()[0];
        $removedChar2 = $players[1]->getRemovedCharacters()[1];
        $this->assertSame(11, $removedChar1->getCharacterId());
        $this->assertSame(12, $removedChar2->getCharacterId());
        $this->assertSame('char2', $removedChar1->getCharacterName());
        $this->assertEqualsWithDelta(time(), $removedChar1->getRemovedDate()->getTimestamp(), 10);
        $this->assertSame($players[0]->getId(), $removedChar1->getNewPlayer()->getId());
        $this->assertSame(RemovedCharacter::REASON_MOVED, $removedChar1->getReason());

        // tests that the new object was persisted.
        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(11, $removedChars[0]->getCharacterId());
        $this->assertSame(12, $removedChars[1]->getCharacterId());
    }

    public function testMergeAccounts_AddsAndUpdatesGroups(): void
    {
        $player1 = (new Player())->setName('player 1');
        $player2 = (new Player())->setName('player 2');
        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');
        $groupAuto1 = (new Group())->setName('auto group 1'); // is removed via autoGroupAssignment
        $groupAuto2 = (new Group())->setName('auto group 2'); // is added via autoGroupAssignment
        $corporation = (new Corporation())->setId(10)->addGroup($groupAuto1)->addGroup($groupAuto2);
        $char1 = (new Character())->setId(10)->setName('char 1')->setPlayer($player1)->setMain(true);
        $char2 = (new Character())->setId(11)->setName('char 2')->setPlayer($player2)->setMain(true)
            ->setCorporation($corporation);
        $player1->addCharacter($char1);
        $player2->addCharacter($char2);
        $player1->addGroup($group1);
        $player2->addGroup($group2);
        $player2->addGroup($groupAuto1);
        $this->om->persist($player1);
        $this->om->persist($player2);
        $this->om->persist($corporation);
        $this->om->persist($char1);
        $this->om->persist($char2);
        $this->om->persist($group1);
        $this->om->persist($group2);
        $this->om->persist($groupAuto1);
        $this->om->persist($groupAuto2);
        $this->om->flush();

        $mergedPlayer = $this->service->mergeAccounts($player2, $player1);
        $this->om->clear();

        $this->assertSame([], $this->log->getMessages());
        $players = $this->playerRepo->findBy([]);
        $this->assertSame($players[0]->getId(), $mergedPlayer->getId());
        $this->assertSame('player 1', $players[0]->getName());
        $this->assertSame('player 2', $players[1]->getName());

        $this->assertSame(
            [$groupAuto1->getId(), $groupAuto2->getId(), $group1->getId(), $group2->getId()],
            $players[0]->getGroupIds(),
        );
        $this->assertSame([$group2->getId()], $players[1]->getGroupIds());
    }

    public function testMergeAccounts_UpdatesServices(): void
    {
        $conf1 = new PluginConfigurationDatabase();
        $conf1->directoryName = 'plugin';
        $conf1->active = true;
        $service1 = (new Plugin())->setName('S1')->setConfigurationDatabase($conf1);

        $player1 = (new Player())->setName('player 1');
        $player2 = (new Player())->setName('player 2');
        $char1 = (new Character())->setId(10)->setName('char 1')->setPlayer($player1)->setMain(true);
        $char2 = (new Character())->setId(11)->setName('char 2')->setPlayer($player2)->setMain(true);
        $player1->addCharacter($char1);
        $player2->addCharacter($char2);
        $this->om->persist($service1);
        $this->om->persist($player1);
        $this->om->persist($player2);
        $this->om->persist($char1);
        $this->om->persist($char2);
        $this->om->flush();

        $mergedPlayer = $this->service->mergeAccounts($player1, $player2);
        $this->om->clear();

        $this->assertSame([], $this->log->getMessages());
        $players = $this->playerRepo->findBy([]);
        $this->assertSame($players[0]->getId(), $mergedPlayer->getId());
        $this->assertSame('player 1', $players[0]->getName());
        $this->assertSame('player 2', $players[1]->getName());
        $this->assertSame([10, 11], TestService::$updateAccount);
    }

    public function testDeleteCharacter(): void
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player)->setMain(true);
        $char2 = (new Character())->setId(11)->setName('char2')->setPlayer($player)->setMain(false);
        $corp = (new Corporation())->setId(1)->setName('c');
        $member = (new CorporationMember())->setId($char->getId())->setCharacter($char)->setCorporation($corp);
        $player->addCharacter($char);
        $player->addCharacter($char2);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->persist($char2);
        $this->om->persist($corp);
        $this->om->persist($member);
        $this->om->flush();

        $this->service->deleteCharacter($char, RemovedCharacter::REASON_DELETED_MANUALLY, $player);
        $this->om->flush();
        $this->om->clear(); // necessary to remove $member and create it again, so that getCharacter() is null

        $this->assertSame(1, count($player->getCharacters()));
        $this->assertSame(1, count($this->charRepo->findAll()));

        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(1, count($removedChars));
        $this->assertSame(10, $removedChars[0]->getCharacterId());
        $this->assertSame('char', $removedChars[0]->getCharacterName());
        $this->assertSame($player->getId(), $removedChars[0]->getPlayer()->getId());
        $this->assertSame('char2', $removedChars[0]->getPlayer()->getName()); // assureMain() changed the name
        $this->assertEqualsWithDelta(time(), $removedChars[0]->getRemovedDate()->getTimestamp(), 10);
        $this->assertNull($removedChars[0]->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_DELETED_MANUALLY, $removedChars[0]->getReason());
        $this->assertSame($player->getId(), $removedChars[0]->getDeletedBy()->getId());
        $this->assertSame(1, count($player->getCharacters()));
        $this->assertSame('char2', $player->getCharacters()[0]->getName());
        $this->assertTrue($player->getCharacters()[0]->getMain());
    }

    public function testDeleteCharacterByAdmin(): void
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $corp = (new Corporation())->setId(1)->setName('c');
        $member = (new CorporationMember())->setId($char->getId())->setCorporation($corp);
        $player->addCharacter($char);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->persist($corp);
        $this->om->persist($member);
        $this->om->flush();

        $this->service->deleteCharacter($char, RemovedCharacter::REASON_DELETED_BY_ADMIN);
        $this->om->flush();

        $this->assertSame(0, count($player->getCharacters()));
        $this->assertSame(0, count($this->charRepo->findAll()));
        $this->assertSame(0, count($this->removedCharRepo->findAll()));

        $this->assertSame(
            'An admin (player ID: unknown) deleted character "char" [10] from player "player 1" [' .
                $player->getId() . ']',
            $this->log->getHandler()->getRecords()[0]['message'],
        );
    }

    public function testAssureMain(): void
    {
        $main = $this->helper->addCharacterMain('Test main', 112);
        $player = $main->getPlayer();
        $alt1 = $this->helper->addCharacterToPlayer('Test alt 1', 113, $player)
            ->setCreated(new \DateTime('2020-05-23 17:41:12'));
        $alt2 = $this->helper->addCharacterToPlayer('Test alt 2', 114, $player)
            ->setCreated(new \DateTime('2020-05-23 16:41:12'));

        $this->service->assureMain($player);

        $this->assertSame(3, count($player->getCharacters()));
        $this->assertTrue($main->getMain());
        $this->assertFalse($alt1->getMain());
        $this->assertFalse($alt2->getMain());

        $player->removeCharacter($main);

        $this->service->assureMain($player);

        $this->assertSame(2, count($player->getCharacters()));
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertTrue($main->getMain());
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertFalse($alt1->getMain());
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        $this->assertTrue($alt2->getMain());
    }

    public function testUpdateGroups(): void
    {
        $this->setUpUpdateGroupsData(); // adds 2 default groups, one with a required group

        $player = $this->helper->addCharacterMain('Player 1', 1, [Role::GROUP_MANAGER])->getPlayer();
        $this->assertSame([Role::GROUP_MANAGER], $player->getRoleNames());

        $result = $this->service->updateGroups($player->getId());
        $this->om->clear();

        $this->assertTrue($result);
        $player = $this->playerRepo->find($player->getId());
        $this->assertSame([], $player->getRoleNames());
        $this->assertSame([$this->group2->getId()], $player->getGroupIds());
    }

    public function testUpdateGroups_guestGroupIsAssignedIfCorporationIsNotInAlliance(): void
    {
        // Setup:
        // - Player is in corporation A.
        // - Corporation A is not in alliance X.
        // - Group 1 if in alliance X.
        // - Group 2 if in corporation A and in group 1 (member group).
        // - Group 3 always, unless in group 2 (guest group).
        $group1 = (new Group())->setName('g1');
        $group2 = (new Group())->setName('g2')->addRequiredGroup($group1);
        $group3 = (new Group())->setName('g3')->setIsDefault(true)->addForbiddenGroup($group2);
        $alliance = (new Alliance())->setId(1)->addGroup($group1);
        $corporation = (new Corporation())->setId(1)->addGroup($group2);
        $player = (new Player())->setName('p1');
        $character = (new Character())->setId(1)->setName('c1')->setMain(true)
            ->setPlayer($player)->setCorporation($corporation);
        $this->om->persist($group1);
        $this->om->persist($group2);
        $this->om->persist($group3);
        $this->om->persist($alliance);
        $this->om->persist($corporation);
        $this->om->persist($player);
        $this->om->persist($character);
        $this->om->flush();
        $this->om->clear();

        $this->service->updateGroups($player->getId());
        $this->om->clear();

        $playerAfter = $this->playerRepo->find($player->getId());
        $this->assertSame([$group3->getId()], $playerAfter->getGroupIds());
    }

    public function testCheckRoles(): void
    {
        $app = (new App())->setName('app')->setSecret('abc');
        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');
        $group3 = (new Group())->setName('group 3');
        $this->om->persist($app);
        $this->om->persist($group1);
        $this->om->persist($group2);
        $this->om->persist($group3);
        $this->om->flush();
        $player = $this->helper->addCharacterMain(
            'Player 1',
            1,
            [Role::GROUP_MANAGER, Role::APP_MANAGER, Role::WATCHLIST_MANAGER, Role::USER_CHARS, Role::ESI],
            ['group 2'],
        )->getPlayer();
        $this->role0->addRequiredGroup($group1); # GROUP_MANAGER
        $this->role3->addRequiredGroup($group1); # APP_MANAGER
        $this->role4->addRequiredGroup($group1); # USER_CHARS
        $this->role5->addRequiredGroup($group2); # ESI
        $this->role5->addRequiredGroup($group3); # ESI
        $player->addManagerApp($app);
        $player->addManagerGroup($group1);
        $this->om->flush();

        $this->service->checkRoles($player);
        $this->om->clear();

        $playerLoaded = $this->playerRepo->find($player->getId());
        $this->assertSame([Role::ESI, Role::WATCHLIST_MANAGER], $playerLoaded->getRoleNames());
        $this->assertSame([], $playerLoaded->getManagerApps());
        $this->assertSame([], $playerLoaded->getManagerGroups());
    }

    public function testMayHaveRole(): void
    {
        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');
        $this->om->persist($group1);
        $this->om->persist($group2);
        $this->role0->addRequiredGroup($group1); # GROUP_MANAGER
        $this->role4->addRequiredGroup($group2); # USER_CHARS
        $player = $this->helper->addCharacterMain(
            'Player 1',
            1,
            [Role::GROUP_MANAGER, Role::APP_MANAGER, Role::WATCHLIST_MANAGER, Role::USER_CHARS, Role::ESI],
            ['group 1'],
        )->getPlayer();

        $this->assertFalse($this->service->mayHaveRole($player, 'invalid'));
        $this->assertTrue($this->service->mayHaveRole($player, Role::APP_MANAGER));
        $this->assertTrue($this->service->mayHaveRole($player, Role::GROUP_MANAGER));
        $this->assertFalse($this->service->mayHaveRole($player, Role::USER_CHARS));
    }

    public function testSyncTrackingRoleInvalidCall(): void
    {
        $this->service->syncTrackingRole();
        $this->service->syncTrackingRole(new Player(), new Corporation());

        $this->assertSame(
            [
                'Account::syncTrackingRole(): Invalid function call.',
                'Account::syncTrackingRole(): Invalid function call.',
            ],
            $this->log->getMessages(),
        );
    }

    public function testSyncTrackingRoleNoRole(): void
    {
        $this->helper->emptyDb();
        $this->service->syncTrackingRole(new Player());

        $this->assertSame(
            "Account::syncRole(): Role 'tracking' not found.",
            $this->log->getHandler()->getRecords()[0]['message'],
        );
    }

    public function testSyncTrackingRoleNoChanged(): void
    {
        $this->setUpTrackingData();

        $this->service->syncTrackingRole(new Player());
        $this->om->flush();

        $players = $this->playerRepo->findBy([]);
        $this->assertSame(2, count($players));
        $this->assertSame('char 1', $players[0]->getName());
        $this->assertSame('char 2', $players[1]->getName());
        $this->assertTrue($players[0]->hasRole(Role::TRACKING));
        $this->assertFalse($players[1]->hasRole(Role::TRACKING));
    }

    public function testSyncTrackingRolePlayerChanged(): void
    {
        $this->setUpTrackingData();

        $this->player1->removeGroup($this->group1);
        $this->player2->addGroup($this->group1);

        $this->service->syncTrackingRole($this->player1);
        $this->service->syncTrackingRole($this->player2);
        $this->om->flush();
        $this->om->clear();

        $players = $this->playerRepo->findBy([]);
        $this->assertFalse($players[0]->hasRole(Role::TRACKING));
        $this->assertTrue($players[1]->hasRole(Role::TRACKING));
    }

    public function testSyncTrackingRoleCorporationChanged(): void
    {
        $this->setUpTrackingData();

        $this->corp1->removeGroupTracking($this->group1);
        $this->corp2->addGroupTracking($this->group2);

        $this->service->syncTrackingRole(null, $this->corp1);
        $this->service->syncTrackingRole(null, $this->corp2);
        $this->om->flush();
        $this->om->clear();

        $players = $this->playerRepo->findBy([]);
        $this->assertFalse($players[0]->hasRole(Role::TRACKING));
        $this->assertTrue($players[1]->hasRole(Role::TRACKING));
    }

    public function testSyncWatchlistRole_NoChange(): void
    {
        $this->setUpWatchlistData();

        $this->service->syncWatchlistRole(new Player());
        $this->om->flush();

        $players = $this->playerRepo->findBy([]);
        $this->assertSame(2, count($players));
        $this->assertSame('char 1', $players[0]->getName());
        $this->assertSame('char 2', $players[1]->getName());
        $this->assertTrue($players[0]->hasRole(Role::WATCHLIST));
        $this->assertFalse($players[1]->hasRole(Role::WATCHLIST));
    }

    public function testSyncWatchlistRole_PlayerChanged(): void
    {
        $this->setUpWatchlistData();

        $this->player1->removeGroup($this->group1);
        $this->player2->addGroup($this->group1);

        $this->service->syncWatchlistRole($this->player1);
        $this->service->syncWatchlistRole($this->player2);
        $this->om->flush();
        $this->om->clear();

        $players = $this->playerRepo->findBy([]);
        $this->assertFalse($players[0]->hasRole(Role::WATCHLIST));
        $this->assertTrue($players[1]->hasRole(Role::WATCHLIST));
    }

    public function testSyncWatchlistRole_GroupChanged(): void
    {
        $this->setUpWatchlistData();

        $this->watchlist1->removeGroup($this->group1);
        $this->watchlist2->addGroup($this->group2);
        $this->om->flush();
        $this->om->clear();

        $this->service->syncWatchlistRole();
        $this->om->flush();
        $this->om->clear();

        $players = $this->playerRepo->findBy([]);
        $this->assertFalse($players[0]->hasRole(Role::WATCHLIST));
        $this->assertTrue($players[1]->hasRole(Role::WATCHLIST));
    }

    public function testSyncWatchlistManagerRole_PlayerChanged(): void
    {
        $this->setUpWatchlistData();
        $this->player2->addGroup($this->group1);

        $this->service->syncWatchlistManagerRole($this->player2);
        $this->om->flush();
        $this->om->clear();

        $players = $this->playerRepo->findBy(['name' => 'char 2']);
        $this->assertSame('char 2', $players[0]->getName());
        $this->assertTrue($players[0]->hasRole(Role::WATCHLIST_MANAGER));
    }

    public function testSyncWatchlistManagerRole_AddsMissingRole(): void
    {
        $this->setUpWatchlistData();

        $this->service->syncWatchlistManagerRole();
        $this->om->flush();
        $this->om->clear();

        $players = $this->playerRepo->findBy([]);
        $this->assertSame('char 1', $players[0]->getName());
        $this->assertSame('char 2', $players[1]->getName());
        $this->assertTrue($players[0]->hasRole(Role::WATCHLIST_MANAGER));
        $this->assertFalse($players[1]->hasRole(Role::WATCHLIST_MANAGER));
    }

    public function testSyncManagerRole_RoleNotFound(): void
    {
        $this->service->syncManagerRole(new Player(), 'name');

        $this->assertSame(
            "Account::syncGroupManagerRole(): Role 'name' not found.",
            $this->log->getHandler()->getRecords()[0]['message'],
        );
    }

    public function testSyncManagerRole(): void
    {
        $role1 = $this->role0;
        $player1 = (new Player())->setName('P1');
        $player2 = (new Player())->setName('P2')->addRole($role1)->addRole($this->role3);
        $group = (new Group())->setName('G')->addManager($player1);
        $app = (new App())->setName('A')->setSecret('abc')->addManager($player1);
        $this->om->persist($role1);
        $this->om->persist($group);
        $this->om->persist($app);
        $this->om->persist($player1);
        $this->om->persist($player2);
        $this->om->flush();
        $this->om->clear();

        $players = $this->playerRepo->findBy([]);
        $this->service->syncManagerRole($players[0], Role::GROUP_MANAGER);
        $this->service->syncManagerRole($players[0], Role::GROUP_MANAGER); // test no error if added twice
        $this->service->syncManagerRole($players[1], Role::GROUP_MANAGER);
        $this->service->syncManagerRole($players[0], Role::APP_MANAGER);
        $this->service->syncManagerRole($players[1], Role::APP_MANAGER);
        $this->om->flush();
        $this->om->clear();

        $players = $this->playerRepo->findBy([]);
        $this->assertSame('P1', $players[0]->getName());
        $this->assertSame('P2', $players[1]->getName());
        $this->assertTrue($players[0]->hasRole(Role::GROUP_MANAGER));
        $this->assertFalse($players[1]->hasRole(Role::GROUP_MANAGER));
        $this->assertTrue($players[0]->hasRole(Role::APP_MANAGER));
        $this->assertFalse($players[1]->hasRole(Role::APP_MANAGER));
    }

    private function setUpUpdateGroupsData(): void
    {
        $group1 = (new Group())->setName('group 1');
        $this->group2 = (new Group())->setName('group 2')->setIsDefault(true);
        $group3 = (new Group())->setName('group 3')->setIsDefault(true)->addRequiredGroup($group1);

        $this->om->persist($group1);
        $this->om->persist($this->group2);
        $this->om->persist($group3);

        $this->om->flush();
    }

    private function setUpTrackingData(): void
    {
        $this->corp1 = (new Corporation())->setId(11)->setTicker('t1')->setName('corp 1');
        $this->corp2 = (new Corporation())->setId(12)->setTicker('t2')->setName('corp 2');
        $member1 = (new CorporationMember())->setId(101)->setName('member 1')->setCorporation($this->corp1);
        $member2 = (new CorporationMember())->setId(102)->setName('member 2')->setCorporation($this->corp2);
        $this->group1 = (new Group())->setName('group 1');
        $this->group2 = (new Group())->setName('group 2');
        $this->corp1->addGroupTracking($this->group1);
        // corp2 does not have tracking group
        $this->om->persist($this->corp1);
        $this->om->persist($this->corp2);
        $this->om->persist($member1);
        $this->om->persist($member2);
        $this->om->persist($this->group1);
        $this->om->persist($this->group2);
        $this->player1 = $this->helper->addCharacterMain('char 1', 101)->getPlayer();
        $this->player2 = $this->helper->addCharacterMain('char 2', 102)->getPlayer();
        $this->player1->addRole($this->role1);
        // player2 does not have tracking role
        $this->player1->addGroup($this->group1);
        $this->player2->addGroup($this->group2);
        $this->om->flush();
    }

    private function setUpWatchlistData(): void
    {
        $this->watchlist1 = (new Watchlist())->setName('wl 1');
        $this->watchlist2 = (new Watchlist())->setName('wl 2');
        $this->group1 = (new Group())->setName('group 1');
        $this->group2 = (new Group())->setName('group 2');
        $this->watchlist1->addGroup($this->group1);
        $this->watchlist1->addManagerGroup($this->group1);
        // watchlist2 does not have an access group
        $this->om->persist($this->watchlist1);
        $this->om->persist($this->watchlist2);
        $this->om->persist($this->group1);
        $this->om->persist($this->group2);
        $this->player1 = $this->helper->addCharacterMain('char 1', 101)->getPlayer();
        $this->player2 = $this->helper->addCharacterMain('char 2', 102)->getPlayer();
        $this->player1->addRole($this->role2);
        // player2 does not have watchlist role
        $this->player1->addGroup($this->group1);
        $this->player2->addGroup($this->group2);
        $this->om->flush();
    }

    private function setUpCharacterWithToken(
        int $expires,
        ?bool $valid = null,
        string $hash = 'hash',
    ): Character {
        $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
        $esiToken = (new EsiToken())->setEveLogin($eveLogin)->setValidToken($valid)
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $char = (new Character())->setId(31)->setName('n31')->setCharacterOwnerHash($hash)
            ->addEsiToken($esiToken);
        $esiToken->setCharacter($char);
        $this->om->persist($eveLogin);
        $this->om->persist($esiToken);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        return $char;
    }

    private function addTokenToChar(string $eveLoginName, Character $char, array $roles, int $expires): EsiToken
    {
        $eveLogin = (new EveLogin())->setName($eveLoginName)->setEveRoles($roles);
        $esiToken = (new EsiToken())->setEveLogin($eveLogin)->setValidToken(true)
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $char->addEsiToken($esiToken);
        $esiToken->setCharacter($char);
        $this->om->persist($eveLogin);
        $this->om->persist($esiToken);
        return $esiToken;
    }
}
