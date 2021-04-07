<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Eve\Sso\EveAuthentication;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Handler\TestHandler;
use Neucore\Entity\Alliance;
use Neucore\Entity\App;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Entity\Watchlist;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterNameChangeRepository;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\CorporationMemberRepository;
use Neucore\Repository\PlayerLoginsRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Repository\RemovedCharacterRepository;
use Neucore\Service\Account;
use Neucore\Service\Config;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\Logger;
use Tests\OAuthProvider;

class AccountTest extends TestCase
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
     * @var Client
     */
    private $client;

    /**
     * @var OAuthToken
     */
    private $token;

    /**
     * @var Account
     */
    private $service;

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var PlayerRepository
     */
    private $playerRepo;

    /**
     * @var RemovedCharacterRepository
     */
    private $removedCharRepo;

    /**
     * @var CorporationMemberRepository
     */
    private $corpMemberRepo;

    /**
     * @var PlayerLoginsRepository
     */
    private $playerLoginsRepo;

    /**
     * @var CharacterNameChangeRepository
     */
    private $characterNameChangeRepo;

    /**
     * @var Player
     */
    private $player1;

    /**
     * @var Player
     */
    private $player2;

    /**
     * @var Corporation
     */
    private $corp1;

    /**
     * @var Corporation
     */
    private $corp2;

    /**
     * @var Watchlist
     */
    private $watchlist1;

    /**
     * @var Watchlist
     */
    private $watchlist2;

    /**
     * @var Group
     */
    private $group1;

    /**
     * @var Group
     */
    private $group2;

    /**
     * @var Role
     */
    private $role1;

    /**
     * @var Role
     */
    private $role2;

    /**
     * @var Role
     */
    private $role3;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        list($this->role1, $this->role2, $this->role3) =
            $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER]);
        $this->om = $this->helper->getObjectManager();

        $this->log = new Logger('Test');
        $this->log->pushHandler(new TestHandler());

        $objectManager = new ObjectManager($this->om, $this->log);

        $this->client = new Client();
        $this->token = new OAuthToken(
            new OAuthProvider($this->client),
            $objectManager,
            $this->log,
            $this->client,
            new Config([])
        );

        $repoFactory = new RepositoryFactory($this->om);

        $this->service = $this->helper->getAccountService($this->log, $this->client);
        $this->charRepo = $repoFactory->getCharacterRepository();
        $this->playerRepo = $repoFactory->getPlayerRepository();
        $this->removedCharRepo = $repoFactory->getRemovedCharacterRepository();
        $this->corpMemberRepo = $repoFactory->getCorporationMemberRepository();
        $this->playerLoginsRepo = $repoFactory->getPlayerLoginsRepository();
        $this->characterNameChangeRepo = $repoFactory->getCharacterNameChangeRepository();
    }

    public function testCreateNewPlayerWithMain()
    {
        $character = $this->service->createNewPlayerWithMain(234, 'bcd');

        $this->assertTrue($character->getMain());
        $this->assertSame(234, $character->getId());
        $this->assertSame('bcd', $character->getName());
        $this->assertSame('bcd', $character->getPlayer()->getName());
        $this->assertSame([], $character->getPlayer()->getRoles());
        $this->assertLessThanOrEqual(time(), $character->getCreated()->getTimestamp());
    }

    public function testMoveCharacterToNewPlayer()
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
        $this->assertSame(RemovedCharacter::REASON_MOVED, $player->getRemovedCharacters()[0]->getReason());
        $this->assertSame($newPlayer, $player->getRemovedCharacters()[0]->getNewPlayer());

        // test relation after persist
        $this->om->flush();
        $this->om->clear();
        $newPlayerLoaded = $this->playerRepo->find($newPlayer->getId());
        $this->assertSame(100, $newPlayerLoaded->getIncomingCharacters()[0]->getCharacterId());
    }

    /**
     * @throws \Exception
     */
    public function testUpdateAndStoreCharacterWithPlayer()
    {
        $player = (new Player())->setName('name');
        $char = (new Character())->setName('char name')->setId(12)->setMain(true);
        $char->setPlayer($player);
        $player->addCharacter($char);

        $this->client->setResponse(
            new Response(200, [], '{"name": "char name changed", "corporation_id": 102}'), // getCharactersCharacterId
            new Response(200, [], '[]'), // postCharactersAffiliation())
            new Response(200, [], '{"name": "name corp", "ticker": "-TC-"}') // getCorporationsCorporationId()
        );

        $expires = time() + (60 * 20);
        $token = Helper::generateToken(['s1', 's2']);
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                100,
                'will be updated because corporation is missing',
                'character-owner-hash',
                new AccessToken(['access_token' => $token[0], 'refresh_token' => 'r-t', 'expires' => $expires])
            ),
            false
        );
        $this->assertTrue($result);

        $this->om->clear();

        $character = $this->charRepo->find(12);

        $this->assertSame('char name changed', $character->getName());
        $this->assertTrue($character->getMain());
        $this->assertSame('char name changed', $player->getName());

        $this->assertSame('character-owner-hash', $character->getCharacterOwnerHash());
        $this->assertSame($token[0], $character->getAccessToken());
        $this->assertSame('r-t', $character->getRefreshToken());
        $this->assertSame($expires, $character->getExpires());
        $this->assertTrue($character->getValidToken());
        $this->assertSame(['s1', 's2'], $character->getScopesFromToken());
        $this->assertSame(102, $character->getCorporation()->getId());
        $this->assertSame('name corp', $character->getCorporation()->getName());
    }

    public function testUpdateAndStoreCharacterWithPlayerNoToken()
    {
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
                new AccessToken(['access_token' => 'a-t'])
            ),
            true
        );
        $this->assertTrue($result);

        $this->om->clear();

        $character = $this->charRepo->find(12);
        $this->assertSame('c-name', $character->getName()); // the name is *not* updated here
        $this->assertNull($character->getRefreshToken());
        $this->assertNull($character->getValidToken());
        $this->assertSame(0, count($character->getCharacterNameChanges()));
    }

    public function testIncreaseLoginCount()
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
        $this->assertSame((int)date('Y'), $logins[0]->getYear());
        $this->assertSame((int)date('m'), $logins[0]->getMonth());
    }

    public function testCheckTokenUpdateCharacterDeletesBiomassedChar()
    {
        $om = $this->om;
        $corp = (new Corporation())->setId(1000001); // Doomheim
        $player = (new Player())->setName('p');
        $char = (new Character())->setId(31)->setName('n31')->setCorporation($corp)->setPlayer($player);
        $om->persist($corp);
        $om->persist($player);
        $om->persist($char);
        $om->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_CHAR_DELETED, $result);

        $om->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
        $this->assertSame(RemovedCharacter::REASON_DELETED_BIOMASSED, $removedChar->getReason());
    }

    public function testCheckCharacter_NoToken()
    {
        $char = (new Character())->setId(100)->setName('name')->setValidToken(false);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_NA, $result);

        $this->om->clear();
        $charLoaded = $this->charRepo->find(100);
        $this->assertNull($charLoaded->getValidToken()); // no token = NULL
    }

    public function testCheckCharacter_InvalidToken()
    {
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(true)
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires(time() - 1000);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(400, [], '{"error": "invalid_grant"}')
        );

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_NOK, $result);

        $this->om->clear();
        $charLoaded = $this->charRepo->find(31);
        $this->assertSame('', $charLoaded->getRefreshToken());
        $this->assertSame('', $charLoaded->getAccessToken());
        $this->assertFalse($charLoaded->getValidToken());
    }

    public function testCheckCharacter_RequestError()
    {
        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(200, [], '{"access_token": "new-at"}'),

            // for getResourceOwner()
            new Response(500)
        );

        $expires = time() - 1000;
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(true)
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_PARSE_ERROR, $result);
        $this->assertTrue($char->getValidToken()); // not changed!
    }

    /**
     * @throws \Exception
     */
    public function testCheckCharacter_ValidTokenNoScopes()
    {
        list($token, $keySet) = Helper::generateToken([]);
        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . json_encode($keySet) . '}') // for SSO JWT key set
        );

        $expires = time() - 1000;
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(true)
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_NOK, $result);

        $this->om->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character->getValidToken());
        $this->assertSame('at', $character->getAccessToken()); // not updated
        $this->assertSame('rt', $character->getRefreshToken()); // not updated
        $this->assertSame($expires, $character->getExpires()); // not updated
    }
    
    /**
     * @throws \Exception
     */
    public function testCheckCharacter_ValidWithScopes()
    {
        list($token, $keySet) = Helper::generateToken(['scope1', 'scope2'], 'Old Name');
        $this->client->setResponse(
            // for getAccessToken()
            new Response(200, [], '{
                "access_token": ' . json_encode($token) . ',
                "expires_in": 1200,
                "refresh_token": "gEy...fM0"
            }'),

            new Response(200, [], '{"keys": ' . json_encode($keySet) . '}') // for SSO JWT key set
        );

        $expires = time() - 1000;
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_OK, $result);

        $this->om->clear();
        $character = $this->charRepo->find(31);
        $this->assertTrue($character->getValidToken());
        $this->assertSame('n31', $character->getName());
        $this->assertSame($token, $character->getAccessToken()); // updated
        $this->assertGreaterThan($expires, $character->getExpires()); // updated
        $this->assertSame('gEy...fM0', $character->getRefreshToken()); // updated
        $characterNameChange = $this->characterNameChangeRepo->findBy([]);
        $this->assertSame(1, count($characterNameChange));
        $this->assertSame('Old Name', $characterNameChange[0]->getOldName());
    }

    /**
     * @throws \Exception
     */
    public function testCheckCharacter_DeletesMovedChar()
    {
        list($token, $keySet) = Helper::generateToken();
        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . json_encode($keySet) . '}') // for SSO JWT key set
        );

        $om = $this->om;
        $expires = time() - 1000;
        $player = (new Player())->setName('p');
        $char = (new Character())
            ->setPlayer($player)
            ->setId(31)->setName('n31')
            ->setCharacterOwnerHash('old-hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $om->persist($player);
        $om->persist($char);
        $om->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_CHAR_DELETED, $result);

        $om->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
        $this->assertSame(RemovedCharacter::REASON_DELETED_OWNER_CHANGED, $removedChar->getReason());
    }

    public function testRemoveCharacterFromPlayer()
    {
        $player = (new Player())->setName('player 1');
        $newPlayer = (new Player())->setName('player 2');
        $char1 = (new Character())->setId(10)->setName('char1')->setPlayer($player)->setMain(true);
        $char2 = (new Character())->setId(11)->setName('char2')->setPlayer($player)->setMain(false);
        $player->addCharacter($char1);
        $player->addCharacter($char2);
        $this->om->persist($player);
        $this->om->persist($newPlayer);
        $this->om->persist($char1);
        $this->om->persist($char2);
        $this->om->flush();

        $this->service->moveCharacter($char1, $newPlayer);

        $this->om->flush();
        $this->om->clear();

        $this->assertSame(1, count($player->getCharacters()));
        $this->assertSame('char2', $player->getCharacters()[1]->getName()); // index 0 was removed
        $this->assertTrue($player->getCharacters()[1]->getMain());
        $removedChar = $player->getRemovedCharacters()[0];
        $this->assertSame(10, $removedChar->getCharacterId());
        $this->assertSame('char1', $removedChar->getCharacterName());
        $this->assertSame('char2', $removedChar->getPlayer()->getName()); // assureMain() changed the name
        $this->assertEqualsWithDelta(time(), $removedChar->getRemovedDate()->getTimestamp(), 10);
        $this->assertSame($newPlayer, $removedChar->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_MOVED, $removedChar->getReason());

        // tests that the new object was persisted.
        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(10, $removedChars[0]->getCharacterId());
    }

    public function testDeleteCharacter()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player)->setMain(true);
        $char2 = (new Character())->setId(11)->setName('char2')->setPlayer($player)->setMain(false);
        $corp = (new Corporation())->setId(1)->setName('c');
        $member = (new CorporationMember())->setId(10)->setCharacter($char)->setCorporation($corp);
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

        $this->assertSame(1, count($player->getCharacters()));
        $this->assertSame(1, count($this->charRepo->findAll()));

        $corpMember = $this->corpMemberRepo->findBy([]);
        $this->assertSame(1, count($corpMember));
        $this->assertNull($corpMember[0]->getCharacter());

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
        $this->assertSame('char2', $player->getCharacters()[1]->getName()); // index 0 was removed
        $this->assertTrue($player->getCharacters()[1]->getMain());
    }

    public function testDeleteCharacterByAdmin()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $corp = (new Corporation())->setId(1)->setName('c');
        $member = (new CorporationMember())->setId(10)->setCharacter($char)->setCorporation($corp);
        $player->addCharacter($char);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->persist($corp);
        $this->om->persist($member);
        $this->om->flush();

        $this->service->deleteCharacter($char, RemovedCharacter::REASON_DELETED_BY_ADMIN);
        $this->om->flush();

        $corpMember = $this->corpMemberRepo->findBy([]);
        $this->assertSame(1, count($corpMember));
        $this->assertNull($corpMember[0]->getCharacter());

        $this->assertSame(0, count($player->getCharacters()));
        $this->assertSame(0, count($this->charRepo->findAll()));
        $this->assertSame(0, count($this->removedCharRepo->findAll()));

        $this->assertSame(
            'An admin (player ID: unknown) deleted character "char" [10] from player "player 1" [' .
                $player->getId() . ']',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testAssureMain()
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
        $this->assertTrue($main->getMain());
        $this->assertFalse($alt1->getMain());
        $this->assertTrue($alt2->getMain());
    }

    public function testGroupsDeactivatedValidToken()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter((new Character())
            ->setValidToken(true)
            ->setCorporation($corporation)
        );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedWrongAllianceAndCorporation()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->flush();

        $alliance = (new Alliance())->setId(12);
        $corporation = (new Corporation())->setId(102);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter((new Character())
            ->setValidToken(false)
            ->setCorporation($corporation)
        );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidToken()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter((new Character())
            ->setValidToken(false)
            ->setCorporation($corporation)
        );

        $this->assertTrue($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenManaged()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())
            ->setStatus(Player::STATUS_MANAGED)
            ->addCharacter((new Character())
                ->setValidToken(false)
                ->setCorporation($corporation)
            );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenWithDelay()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $setting4 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY))->setValue('24');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->persist($setting4);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter((new Character())
            ->setValidToken(false)
            ->setValidTokenTime(new \DateTime("now -12 hours"))
            ->setCorporation($corporation)
        );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenIgnoreDelay()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $setting4 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY))->setValue('24');
        $this->om->persist($setting1);
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->persist($setting4);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter((new Character())
            ->setValidToken(false)
            ->setValidTokenTime(new \DateTime("now -12 hours"))
            ->setCorporation($corporation)
        );

        $this->assertTrue($this->service->groupsDeactivated($player, true));
    }

    public function testGroupsDeactivatedInvalidTokenSettingNotActive()
    {
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->om->persist($setting2);
        $this->om->persist($setting3);
        $this->om->flush();

        $alliance = (new Alliance())->setId(11);
        $corporation = (new Corporation())->setId(101);
        $corporation->setAlliance($alliance);
        $player = (new Player())->addCharacter((new Character())
            ->setValidToken(false)
            ->setCorporation($corporation)
        );

        // test with missing setting
        $this->assertFalse($this->service->groupsDeactivated($player));

        // add "deactivated groups" setting set to 0
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('0');
        $this->om->persist($setting1);
        $this->om->flush();

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testUpdateGroups()
    {
        $player = $this->helper->addCharacterMain('Player 1', 1, [Role::GROUP_MANAGER])->getPlayer();
        $this->assertSame([Role::GROUP_MANAGER], $player->getRoleNames());

        $result = $this->service->updateGroups($player->getId());
        $this->om->clear();

        $this->assertTrue($result);
        $player = $this->playerRepo->find($player->getId());
        $this->assertSame([], $player->getRoleNames());
    }

    public function testSyncTrackingRoleInvalidCall()
    {
        $this->service->syncTrackingRole();
        $this->service->syncTrackingRole(new Player, new Corporation);
        
        $this->assertSame(
            'Account::syncTrackingRole(): Invalid function call.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
        $this->assertSame(
            'Account::syncTrackingRole(): Invalid function call.',
            $this->log->getHandler()->getRecords()[1]['message']
        );
    }

    public function testSyncTrackingRoleNoRole()
    {
        $this->helper->emptyDb();
        $this->service->syncTrackingRole(new Player());

        $this->assertSame(
            "Account::syncRole(): Role 'tracking' not found.",
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testSyncTrackingRoleNoChanged()
    {
        $this->setUpTrackingData();

        $this->service->syncTrackingRole(new Player);
        $this->om->flush();

        $players = $this->playerRepo->findBy([]);
        $this->assertSame(2, count($players));
        $this->assertSame('char 1', $players[0]->getName());
        $this->assertSame('char 2', $players[1]->getName());
        $this->assertTrue($players[0]->hasRole(Role::TRACKING));
        $this->assertFalse($players[1]->hasRole(Role::TRACKING));
    }
    
    public function testSyncTrackingRolePlayerChanged()
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
    
    public function testSyncTrackingRoleCorporationChanged()
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

    public function testSyncWatchlistRole_NoChange()
    {
        $this->setUpWatchlistData();

        $this->service->syncWatchlistRole(new Player);
        $this->om->flush();

        $players = $this->playerRepo->findBy([]);
        $this->assertSame(2, count($players));
        $this->assertSame('char 1', $players[0]->getName());
        $this->assertSame('char 2', $players[1]->getName());
        $this->assertTrue($players[0]->hasRole(Role::WATCHLIST));
        $this->assertFalse($players[1]->hasRole(Role::WATCHLIST));
    }

    public function testSyncWatchlistRole_PlayerChanged()
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

    public function testSyncWatchlistRole_GroupChanged()
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

    public function testSyncWatchlistManagerRole_PlayerChanged()
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

    public function testSyncWatchlistManagerRole_AddsMissingRole()
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

    public function testSyncManagerRole_RoleNotFound()
    {
        $this->service->syncManagerRole(new Player, 'name');

        $this->assertSame(
            'Account::syncGroupManagerRole(): Role not found.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testSyncManagerRole()
    {
        $role1 = (new Role(10))->setName(Role::GROUP_MANAGER);
        $role2 = (new Role(11))->setName(Role::APP_MANAGER);
        $player1 = (new Player())->setName('P1');
        $player2 = (new Player())->setName('P2')->addRole($role1)->addRole($role2);
        $group = (new Group())->setName('G')->addManager($player1);
        $app = (new App())->setName('A')->setSecret('abc')->addManager($player1);
        $this->om->persist($role1);
        $this->om->persist($role2);
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
}
