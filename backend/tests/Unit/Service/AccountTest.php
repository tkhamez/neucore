<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Group;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Entity\Watchlist;
use Neucore\Repository\CharacterRepository;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CorporationMemberRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Repository\RemovedCharacterRepository;
use Neucore\Service\Account;
use Neucore\Service\Config;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use Brave\Sso\Basics\EveAuthentication;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\OAuthProvider;
use Tests\Client;

class AccountTest extends TestCase
{
    /**
     * @var Helper
     */
    private $helper;

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

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $em = $this->helper->getEm();

        $this->log = new Logger('Test');
        $this->log->pushHandler(new TestHandler());

        $om = new ObjectManager($em, $this->log);

        $this->client = new Client();
        $this->token = new OAuthToken(new OAuthProvider($this->client), $om, $this->log, $this->client, new Config([]));
        $this->service = new Account($this->log, $om, new RepositoryFactory($em));
        $this->charRepo = (new RepositoryFactory($em))->getCharacterRepository();
        $this->playerRepo = (new RepositoryFactory($em))->getPlayerRepository();
        $this->removedCharRepo = (new RepositoryFactory($em))->getRemovedCharacterRepository();
        $this->corpMemberRepo = (new RepositoryFactory($em))->getCorporationMemberRepository();
    }

    public function testCreateNewPlayerWithMain()
    {
        $character = $this->service->createNewPlayerWithMain(234, 'bcd');

        $this->assertTrue($character->getMain());
        $this->assertSame(234, $character->getId());
        $this->assertSame('bcd', $character->getName());
        $this->assertSame('bcd', $character->getPlayer()->getName());
        $this->assertSame([], $character->getPlayer()->getRoles());
        $this->assertGreaterThanOrEqual(time(), $character->getCreated()->getTimestamp());
    }

    public function testMoveCharacterToNewPlayer()
    {
        $char = new Character();
        $char->setId(100);
        $char->setName('char name');
        $player = new Player();
        $player->setName($char->getName());
        $player->addCharacter($char);
        $char->setPlayer($player);

        $character = $this->service->moveCharacterToNewAccount($char);

        $newPlayer = $character->getPlayer();

        $this->assertSame($char, $character);
        $this->assertNotSame($player, $newPlayer);
        $this->assertSame('char name', $newPlayer->getName());

        $this->assertSame(100, $player->getRemovedCharacters()[0]->getCharacterId());
        $this->assertSame($newPlayer, $player->getRemovedCharacters()[0]->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_MOVED, $player->getRemovedCharacters()[0]->getReason());
        $this->assertSame($newPlayer, $player->getRemovedCharacters()[0]->getNewPlayer());

        // test relation after persist
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->persist($character);
        $this->helper->getEm()->persist($character->getPlayer());
        $this->helper->getEm()->flush();
        $this->helper->getEm()->clear();
        $newPlayerLoaded = $this->playerRepo->find($newPlayer->getId());
        $this->assertSame(100, $newPlayerLoaded->getIncomingCharacters()[0]->getCharacterId());
    }

    public function testUpdateAndStoreCharacterWithPlayer()
    {
        $player = (new Player())->setName('name');
        $char = (new Character())->setName('char name')->setId(12)->setMain(true);
        $char->setPlayer($player);
        $player->addCharacter($char);

        $expires = time() + (60 * 20);
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                100,
                'char name changed',
                'character-owner-hash',
                new AccessToken(['access_token' => 'a-t', 'refresh_token' => 'r-t', 'expires' => $expires]),
                ['scope1', 'scope2']
            )
        );
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $character = $this->charRepo->find(12);

        $this->assertSame('char name changed', $character->getName());
        $this->assertTrue($character->getMain());
        $this->assertSame('char name changed', $player->getName());

        $this->assertSame('character-owner-hash', $character->getCharacterOwnerHash());
        $this->assertSame('a-t', $character->getAccessToken());
        $this->assertSame('r-t', $character->getRefreshToken());
        $this->assertSame($expires, $character->getExpires());
        $this->assertSame('scope1 scope2', $character->getScopes());
        $this->assertTrue($character->getValidToken());
    }

    public function testUpdateAndStoreCharacterWithPlayerNoToken()
    {
        $player = (new Player())->setName('p-name');
        $char = (new Character())->setName('c-name')->setId(12)->setPlayer($player);

        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                100,
                'name',
                'character-owner-hash',
                new AccessToken(['access_token' => 'a-t']),
                []
            )
        );
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $character = $this->charRepo->find(12);
        $this->assertNull($character->getRefreshToken());
        $this->assertNull($character->getValidToken());
    }

    public function testCheckTokenUpdateCharacterDeletesBiomassedChar()
    {
        $em = $this->helper->getEm();
        $corp = (new Corporation())->setId(1000001); // Doomheim
        $player = (new Player())->setName('p');
        $char = (new Character())->setId(31)->setName('n31')->setCorporation($corp)->setPlayer($player);
        $em->persist($corp);
        $em->persist($player);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_CHAR_DELETED, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
        $this->assertSame(RemovedCharacter::REASON_DELETED_BIOMASSED, $removedChar->getReason());
    }

    public function testCheckCharacterNoToken()
    {
        $char = (new Character())->setId(100)->setName('name')->setValidToken(false);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_NA, $result);

        $this->assertNull($char->getValidToken()); // no token = NULL
    }

    public function testCheckCharacterInvalidToken()
    {
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires(time() - 1000);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(400, [], '{"error": "invalid_grant"}')
        );

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_NOK, $result);
    }

    public function testCheckCharacterRequestError()
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
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_PARSE_ERROR, $result);
    }

    /**
     * @throws \Exception
     */
    public function testCheckCharacterValidTokenNoScopes()
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

        $this->helper->getEm()->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character->getValidToken());
        $this->assertSame('at', $character->getAccessToken()); // not updated
        $this->assertSame('rt', $character->getRefreshToken()); // not updated
        $this->assertSame($expires, $character->getExpires()); // not updated
    }
    
    /**
     * @throws \Exception
     */
    public function testCheckCharacterValid()
    {
        list($token, $keySet) = Helper::generateToken(['scope1', 'scope2']);
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
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires)
            ->setScopes('scope1 scope2');
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_OK, $result);

        $this->helper->getEm()->clear();
        $character = $this->charRepo->find(31);
        $this->assertTrue($character->getValidToken());
        $this->assertSame($token, $character->getAccessToken()); // updated
        $this->assertGreaterThan($expires, $character->getExpires()); // updated
        $this->assertSame('gEy...fM0', $character->getRefreshToken()); // updated
    }

    /**
     * @throws \Exception
     */
    public function testCheckCharacterDeletesMovedChar()
    {
        list($token, $keySet) = Helper::generateToken();
        $this->client->setResponse(
            new Response(200, [], '{"access_token": ' . json_encode($token) . '}'), // for getAccessToken()
            new Response(200, [], '{"keys": ' . json_encode($keySet) . '}') // for SSO JWT key set
        );

        $em = $this->helper->getEm();
        $expires = time() - 1000;
        $player = (new Player())->setName('p');
        $char = (new Character())
            ->setPlayer($player)
            ->setId(31)->setName('n31')
            ->setCharacterOwnerHash('old-hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $em->persist($player);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_CHAR_DELETED, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
        $this->assertSame(RemovedCharacter::REASON_DELETED_OWNER_CHANGED, $removedChar->getReason());
    }

    public function testRemoveCharacterFromPlayer()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $player->addCharacter($char);
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->flush();

        $this->service->removeCharacterFromPlayer($char, $player);
        $this->helper->getEm()->flush();

        $this->helper->getEm()->clear();

        $this->assertSame(0, count($player->getCharacters()));
        $this->assertSame(10, $player->getRemovedCharacters()[0]->getCharacterId());
        $this->assertSame('char', $player->getRemovedCharacters()[0]->getCharacterName());
        $this->assertSame('player 1', $player->getRemovedCharacters()[0]->getPlayer()->getName());
        $this->assertEqualsWithDelta(time(), $player->getRemovedCharacters()[0]->getRemovedDate()->getTimestamp(), 10);
        $this->assertSame($player, $player->getRemovedCharacters()[0]->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_MOVED, $player->getRemovedCharacters()[0]->getReason());

        // tests that the new object was persisted.
        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(10, $removedChars[0]->getCharacterId());
    }

    public function testDeleteCharacter()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $corp = (new Corporation())->setId(1)->setName('c');
        $member = (new CorporationMember())->setId(10)->setCharacter($char)->setCorporation($corp);
        $player->addCharacter($char);
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->persist($corp);
        $this->helper->getEm()->persist($member);
        $this->helper->getEm()->flush();

        $this->service->deleteCharacter($char, RemovedCharacter::REASON_DELETED_MANUALLY, $player);
        $this->helper->getEm()->flush();

        $this->assertSame(0, count($player->getCharacters()));
        $this->assertSame(0, count($this->charRepo->findAll()));

        $corpMember = $this->corpMemberRepo->findBy([]);
        $this->assertSame(1, count($corpMember));
        $this->assertNull($corpMember[0]->getCharacter());

        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(1, count($removedChars));
        $this->assertSame(10, $removedChars[0]->getCharacterId());
        $this->assertSame('char', $removedChars[0]->getCharacterName());
        $this->assertSame($player->getId(), $removedChars[0]->getPlayer()->getId());
        $this->assertSame('player 1', $removedChars[0]->getPlayer()->getName());
        $this->assertEqualsWithDelta(time(), $removedChars[0]->getRemovedDate()->getTimestamp(), 10);
        $this->assertNull($removedChars[0]->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_DELETED_MANUALLY, $removedChars[0]->getReason());
        $this->assertSame($player->getId(), $removedChars[0]->getDeletedBy()->getId());
    }

    public function testDeleteCharacterByAdmin()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $corp = (new Corporation())->setId(1)->setName('c');
        $member = (new CorporationMember())->setId(10)->setCharacter($char)->setCorporation($corp);
        $player->addCharacter($char);
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->persist($corp);
        $this->helper->getEm()->persist($member);
        $this->helper->getEm()->flush();

        $this->service->deleteCharacter($char, RemovedCharacter::REASON_DELETED_BY_ADMIN);
        $this->helper->getEm()->flush();

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

    public function testGroupsDeactivatedValidToken()
    {
        $setting1 = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('11');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('101');
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->flush();

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
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->flush();

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
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->flush();

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
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->flush();

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
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->persist($setting4);
        $this->helper->getEm()->flush();

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
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->persist($setting4);
        $this->helper->getEm()->flush();

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
        $this->helper->getEm()->persist($setting2);
        $this->helper->getEm()->persist($setting3);
        $this->helper->getEm()->flush();

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
        $this->helper->getEm()->persist($setting1);
        $this->helper->getEm()->flush();

        $this->assertFalse($this->service->groupsDeactivated($player));
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
        $this->service->syncTrackingRole(new Player());

        $this->assertSame(
            'Account::syncTrackingRole(): Role not found.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testSyncTrackingRoleNoChanged()
    {
        $this->setUpTrackingData();

        $this->service->syncTrackingRole(new Player);
        $this->helper->getEm()->flush();

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
        $this->helper->getEm()->flush();
        $this->helper->getEm()->clear();

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
        $this->helper->getEm()->flush();
        $this->helper->getEm()->clear();

        $players = $this->playerRepo->findBy([]);
        $this->assertFalse($players[0]->hasRole(Role::TRACKING));
        $this->assertTrue($players[1]->hasRole(Role::TRACKING));
    }

    public function testSyncWatchlistRoleNoRole()
    {
        $this->service->syncWatchlistRole(new Player());

        $this->assertSame(
            'Account::syncWatchlistRole(): Role not found.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testSyncWatchlistRoleNoChanged()
    {
        $this->setUpWatchlistData();

        $this->service->syncWatchlistRole(new Player);
        $this->helper->getEm()->flush();

        $players = $this->playerRepo->findBy([]);
        $this->assertSame(2, count($players));
        $this->assertSame('char 1', $players[0]->getName());
        $this->assertSame('char 2', $players[1]->getName());
        $this->assertTrue($players[0]->hasRole(Role::WATCHLIST));
        $this->assertFalse($players[1]->hasRole(Role::WATCHLIST));
    }

    public function testSyncWatchlistRolePlayerChanged()
    {
        $this->setUpWatchlistData();

        $this->player1->removeGroup($this->group1);
        $this->player2->addGroup($this->group1);

        $this->service->syncWatchlistRole($this->player1);
        $this->service->syncWatchlistRole($this->player2);
        $this->helper->getEm()->flush();
        $this->helper->getEm()->clear();

        $players = $this->playerRepo->findBy([]);
        $this->assertFalse($players[0]->hasRole(Role::WATCHLIST));
        $this->assertTrue($players[1]->hasRole(Role::WATCHLIST));
    }

    public function testSyncWatchlistRoleGroupChanged()
    {
        $this->setUpWatchlistData();

        $this->watchlist1->removeGroup($this->group1);
        $this->watchlist2->addGroup($this->group2);
        $this->helper->getEm()->flush();
        $this->helper->getEm()->clear();

        $this->service->syncWatchlistRole();
        $this->helper->getEm()->flush();
        $this->helper->getEm()->clear();

        $players = $this->playerRepo->findBy([]);
        $this->assertFalse($players[0]->hasRole(Role::WATCHLIST));
        $this->assertTrue($players[1]->hasRole(Role::WATCHLIST));
    }

    private function setUpTrackingData()
    {
        $em = $this->helper->getEm();
        
        $role = (new Role(10))->setName(Role::TRACKING);
        $this->corp1 = (new Corporation())->setId(11)->setTicker('t1')->setName('corp 1');
        $this->corp2 = (new Corporation())->setId(12)->setTicker('t2')->setName('corp 2');
        $member1 = (new CorporationMember())->setId(101)->setName('member 1')->setCorporation($this->corp1);
        $member2 = (new CorporationMember())->setId(102)->setName('member 2')->setCorporation($this->corp2);
        $this->group1 = (new Group())->setName('group 1');
        $this->group2 = (new Group())->setName('group 2');
        $this->corp1->addGroupTracking($this->group1);
        // corp2 does not have tracking group
        $em->persist($role);
        $em->persist($this->corp1);
        $em->persist($this->corp2);
        $em->persist($member1);
        $em->persist($member2);
        $em->persist($this->group1);
        $em->persist($this->group2);
        $this->player1 = $this->helper->addCharacterMain('char 1', 101)->getPlayer();
        $this->player2 = $this->helper->addCharacterMain('char 2', 102)->getPlayer();
        $this->player1->addRole($role);
        // player2 does not have tracking role
        $this->player1->addGroup($this->group1);
        $this->player2->addGroup($this->group2);
        $em->flush();
    }

    private function setUpWatchlistData()
    {
        $em = $this->helper->getEm();

        $role = (new Role(10))->setName(Role::WATCHLIST);
        $this->watchlist1 = (new Watchlist())->setId(11)->setName('wl 1');
        $this->watchlist2 = (new Watchlist())->setId(12)->setName('wl 2');
        $this->group1 = (new Group())->setName('group 1');
        $this->group2 = (new Group())->setName('group 2');
        $this->watchlist1->addGroup($this->group1);
        // watchlist2 does not have an access group
        $em->persist($role);
        $em->persist($this->watchlist1);
        $em->persist($this->watchlist2);
        $em->persist($this->group1);
        $em->persist($this->group2);
        $this->player1 = $this->helper->addCharacterMain('char 1', 101)->getPlayer();
        $this->player2 = $this->helper->addCharacterMain('char 2', 102)->getPlayer();
        $this->player1->addRole($role);
        // player2 does not have watchlist role
        $this->player1->addGroup($this->group1);
        $this->player2->addGroup($this->group2);
        $em->flush();
    }
}
