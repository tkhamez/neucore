<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Entity\Alliance;
use Neucore\Entity\CharacterNameChange;
use Neucore\Entity\Corporation;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\Plugin;
use Neucore\Entity\SystemVariable;
use Neucore\Entity\Watchlist;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Data\ServiceAccountData;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\CorporationRepository;
use Neucore\Repository\GroupApplicationRepository;
use Neucore\Repository\GroupRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Repository\RemovedCharacterRepository;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class PlayerControllerTest extends WebTestCase
{
    private static WriteErrorListener $writeErrorListener;

    private Helper $h;

    private EntityManagerInterface $em;

    private int $player1Id;

    private int $player4Id;

    private int $player5;

    private int $noPlayerId = 0;

    private int $eveLoginId;

    private int $managerId;

    private int $emptyAccId;

    private int $corpId;

    private int $player3Id;

    private int $groupId;

    private int $gPrivateId;

    private int $requiredGroupId;

    private int $autoAcceptGroupId;

    private int $autoAssignGroupId;

    private PlayerRepository $playerRepo;

    private CharacterRepository $charRepo;

    private CorporationRepository $corpRepo;

    private RemovedCharacterRepository $removedCharRepo;

    private GroupApplicationRepository $groupAppRepo;

    private GroupRepository $groupRepo;

    private Logger $log;

    private string $date1 = '2019-08-03T23:12:45Z';

    public static function setupBeforeClass(): void
    {
        self::$writeErrorListener = new WriteErrorListener();
    }

    protected function setUp(): void
    {
        $_SESSION = null;

        $this->h = new Helper();
        $this->em = $this->h->getEm();

        $rf = new RepositoryFactory($this->em);
        $this->playerRepo = $rf->getPlayerRepository();
        $this->charRepo = $rf->getCharacterRepository();
        $this->corpRepo = $rf->getCorporationRepository();
        $this->removedCharRepo = $rf->getRemovedCharacterRepository();
        $this->groupAppRepo = $rf->getGroupApplicationRepository();
        $this->groupRepo = $rf->getGroupRepository();

        $this->log = new Logger();
    }

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testShow403()
    {
        $response = $this->runApp('GET', '/api/user/player/show');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShow200()
    {
        $this->h->emptyDb();
        $groups = $this->h->addGroups(['group1', 'another-group']);
        $char = $this->h->addCharacterMain(
            'TUser',
            123456,
            [Role::USER, Role::USER_ADMIN],
            ['group1', 'another-group']
        );
        $alli = (new Alliance())->setId(123)->setName('alli1')->setTicker('ATT');
        $corp = (new Corporation())->setId(456)->setName('corp1')->setTicker('MT')->setAlliance($alli);
        $char->setCorporation($corp);
        $this->em->persist($alli);
        $this->em->persist($corp);
        $this->em->flush();
        $this->em->clear();
        $this->loginUser(123456);

        $response = $this->runApp('GET', '/api/user/player/show');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $char->getPlayer()->getId(),
            'name' => 'TUser',
            'status' => Player::STATUS_STANDARD,
            'roles' => [Role::USER, Role::USER_ADMIN],
            'characters' => [[
                'id' => 123456,
                'name' => 'TUser',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => null,
                'validTokenTime' => null,
                'tokenLastChecked' => null,
                'corporation' => [
                    'id' => 456, 'name' => 'corp1', 'ticker' => 'MT', 'alliance' => [
                        'id' => 123, 'name' => 'alli1', 'ticker' => 'ATT'
                    ]
                ],
                'esiTokens' => [[
                    'eveLoginId' => $char->getEsiTokens()[0]->getEveLogin()->getId(),
                    'characterId' => 123456,
                    'playerId' => $char->getPlayer()->getId(),
                    'validToken' => null,
                    'validTokenTime' => null,
                    'hasRoles' => null,
                    'lastChecked' => null,
                ]],
            ]],
            'groups' => [
                ['id' => $groups[1]->getId(), 'name' => 'another-group', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false],
                ['id' => $groups[0]->getId(), 'name' => 'group1', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false]
            ],
            'managerGroups' => [],
            'managerApps' => [],
        ], $this->parseJsonBody($response));
    }

    public function testAddApplication403()
    {
        $response = $this->runApp('PUT', '/api/user/player/add-application/11');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddApplication404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('PUT', '/api/user/player/add-application/' . ($this->groupId + 5));
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/player/add-application/' . $this->gPrivateId);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddApplication400()
    {
        $this->setupDb();
        $this->loginUser(10);

        $response = $this->runApp('PUT', '/api/user/player/add-application/'. $this->groupId);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddApplication204()
    {
        $this->setupDb();
        $this->loginUser(12);

        // creates application
        $response1 = $this->runApp('PUT', '/api/user/player/add-application/'. $this->groupId);
        $this->assertEquals(204, $response1->getStatusCode());

        $this->em->clear();

        // updates application
        $response2 = $this->runApp('PUT', '/api/user/player/add-application/'. $this->groupId);
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $groupApps = $this->groupAppRepo->findBy([]);
        $player = $this->playerRepo->find($this->player3Id);
        $this->assertSame(1, count($groupApps));
        $this->assertSame($this->groupId, $groupApps[0]->getGroup()->getId());
        $this->assertSame(GroupApplication::STATUS_PENDING, $groupApps[0]->getStatus());
        $this->assertSame([$this->requiredGroupId], $player->getGroupIds());
        $this->assertSame($this->player3Id, $groupApps[0]->getPlayer()->getId());
        $this->assertLessThanOrEqual(time(), $groupApps[0]->getCreated()->getTimestamp());
    }

    public function testAddApplication204_AutoAccept()
    {
        $this->setupDb();
        $this->loginUser(12);

        // creates application
        $response = $this->runApp('PUT', '/api/user/player/add-application/'. $this->autoAcceptGroupId);
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $groupApps = $this->groupAppRepo->findBy([]);
        $player = $this->playerRepo->find($this->player3Id);
        $this->assertSame(1, count($groupApps));
        $this->assertSame($this->autoAcceptGroupId, $groupApps[0]->getGroup()->getId());
        $this->assertSame(GroupApplication::STATUS_ACCEPTED, $groupApps[0]->getStatus());
        $this->assertSame([$this->autoAcceptGroupId, $this->requiredGroupId], $player->getGroupIds());
    }

    public function testRemoveApplication403()
    {
        $response = $this->runApp('PUT', '/api/user/player/remove-application/11');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveApplication404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('PUT', '/api/user/player/remove-application/' . ($this->groupId + 5));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveApplication204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $ga = new GroupApplication();
        $ga->setGroup($this->fetchGroup($this->groupId));
        $ga->setPlayer($this->fetchPlayer($this->player3Id));
        $this->em->persist($ga);
        $this->em->flush();

        $response = $this->runApp('PUT', '/api/user/player/remove-application/' . $this->groupId);
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $groupApps = $this->groupAppRepo->findBy([]);
        $this->assertSame(0, count($groupApps));
    }

    public function testShowApplications403()
    {
        $response = $this->runApp('GET', '/api/user/player/show-applications');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShowApplications200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $ga = new GroupApplication();
        $ga->setGroup($this->fetchGroup($this->groupId));
        $ga->setPlayer($this->fetchPlayer($this->player3Id));
        $this->em->persist($ga);
        $this->em->flush();

        $response = $this->runApp('GET', '/api/user/player/show-applications');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([[
            'id' => $ga->getId(),
            'player' => ['id' => $this->player3Id, 'name' => 'Admin'],
            'group' => ['id' => $this->groupId, 'name' => 'test-pub', 'description' => null,
                'visibility' => Group::VISIBILITY_PUBLIC, 'autoAccept' => false, 'isDefault' => false],
            'status' => GroupApplication::STATUS_PENDING,
            'created' => null,
        ]], $this->parseJsonBody($response));
    }

    public function testLeaveGroup403()
    {
        $response = $this->runApp('PUT', '/api/user/player/leave-group/11');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testLeaveGroup404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('PUT', '/api/user/player/leave-group/' . ($this->groupId + 5));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testLeaveGroup204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $ga = new GroupApplication();
        $ga->setGroup($this->fetchGroup($this->groupId));
        $ga->setPlayer($this->fetchPlayer($this->player3Id));
        $this->em->persist($ga);
        $this->em->flush();

        $this->fetchPlayer($this->player3Id)->addGroup($this->fetchGroup($this->groupId));
        $this->em->flush();

        $response = $this->runApp('PUT', '/api/user/player/leave-group/' . $this->groupId);
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $p = $this->fetchPlayer($this->player3Id);
        $this->assertSame(1, count($p->getGroups()));
        $groupApps = $this->groupAppRepo->findBy([]);
        $this->assertSame(0, count($groupApps));
    }

    public function testSetMain403()
    {
        $response = $this->runApp('PUT', '/api/user/player/set-main/123456');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSetMain404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('PUT', '/api/user/player/set-main/123456');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSetMain200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $charsBefore = $this->fetchPlayer($this->player3Id)->getCharacters();
        $this->assertSame(12, $charsBefore[0]->getId());
        $this->assertSame(13, $charsBefore[1]->getId());
        $this->assertTrue($charsBefore[0]->getMain());
        $this->assertFalse($charsBefore[1]->getMain());
        $this->assertSame('Alt', $charsBefore[1]->getName());
        $this->assertSame('Admin', $charsBefore[0]->getName());

        $response = $this->runApp('PUT', '/api/user/player/set-main/13');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            ['id' => 13,
                'name' => 'Alt',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => true,
                'validTokenTime' => '2019-08-03T23:12:46Z',
                'tokenLastChecked' => null,
                'corporation' => null
            ],
            $this->parseJsonBody($response)
        );

        $this->em->clear();

        $playerAfter = $this->fetchPlayer($this->player3Id);
        $charsAfter = $playerAfter->getCharacters();
        $this->assertSame(12, $charsAfter[0]->getId());
        $this->assertSame(13, $charsAfter[1]->getId());
        $this->assertFalse($charsAfter[0]->getMain());
        $this->assertTrue($charsAfter[1]->getMain());
        $this->assertSame('Alt', $playerAfter->getName());
    }

    public function testSetStatus403()
    {
        $this->setupDb();

        $response1 = $this->runApp('PUT', '/api/user/player/'.$this->managerId.'/set-status/'.Player::STATUS_MANAGED);
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(12); // user-admin etc., not a user-manager

        $response2 = $this->runApp('PUT', '/api/user/player/'.$this->managerId.'/set-status/'.Player::STATUS_MANAGED);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testSetStatus400()
    {
        $this->setupDb();
        $this->loginUser(14); // has role user-manager

        $response1 = $this->runApp('PUT', '/api/user/player/'.$this->managerId.'/set-status/invalid');
        $this->assertEquals(400, $response1->getStatusCode());

        $response2 = $this->runApp('PUT', '/api/user/player/99/set-status/managed');
        $this->assertEquals(400, $response2->getStatusCode());
    }

    public function testSetStatus204()
    {
        $this->setupDb();
        $this->loginUser(14); // has role user-manager

        $player = $this->playerRepo->find($this->managerId);
        $this->assertSame(Player::STATUS_MANAGED, $player->getStatus());
        $this->assertSame(1, count($player->getGroups()));

        $response1 = $this->runApp('PUT', '/api/user/player/'.$this->managerId.'/set-status/'.Player::STATUS_STANDARD);

        $this->assertEquals(204, $response1->getStatusCode());
        $this->em->clear();
        $player2 = $this->playerRepo->find($this->managerId);
        $this->assertSame(Player::STATUS_STANDARD, $player2->getStatus());
        $this->assertSame(0, count($player2->getGroups()));
    }

    public function testWithCharacters403()
    {
        $response = $this->runApp('GET', '/api/user/player/with-characters');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin

        $response = $this->runApp('GET', '/api/user/player/with-characters');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWithCharacters200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/with-characters');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            ['id' => $this->player5, 'name' => 'Account with no main'],
            ['id' => $this->player3Id, 'name' => 'Admin'],
            ['id' => $this->managerId, 'name' => 'Manager'],
            ['id' => $this->player1Id, 'name' => 'User'],
            ['id' => $this->player4Id, 'name' => 'User3'],
        ], $this->parseJsonBody($response));
    }

    public function testWithoutCharacters403()
    {
        $response = $this->runApp('GET', '/api/user/player/without-characters');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin

        $response = $this->runApp('GET', '/api/user/player/without-characters');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWithoutCharacters200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/without-characters');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            ['id' => $this->emptyAccId, 'name' => 'empty account'],
        ], $this->parseJsonBody($response));
    }

    public function testAppManagers403()
    {
        $response = $this->runApp('GET', '/api/user/player/app-managers');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin or group-admin

        $response = $this->runApp('GET', '/api/user/player/app-managers');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAppManagers200NoGroup()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('Admin', 12, [Role::APP_ADMIN]);
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/app-managers', null, null, [
            LoggerInterface::class => $this->log
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response));
        $this->assertSame(
            'PlayerController->getManagers(): role "app-manager" not found.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testAppManagers200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/app-managers');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->managerId, 'name' => 'Manager']],
            $this->parseJsonBody($response)
        );
    }

    public function testGroupManagers403()
    {
        $response = $this->runApp('GET', '/api/user/player/group-managers');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin or group-admin

        $response = $this->runApp('GET', '/api/user/player/group-managers');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupManagers200NoGroup()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('Admin', 12, [Role::GROUP_ADMIN]);
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/group-managers', null, null, [
            LoggerInterface::class => $this->log
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], $this->parseJsonBody($response));
        $this->assertSame(
            'PlayerController->getManagers(): role "group-manager" not found.',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testGroupManagers200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/group-managers');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->managerId, 'name' => 'Manager']],
            $this->parseJsonBody($response)
        );
    }

    public function testWithRole403()
    {
        $response = $this->runApp('GET', '/api/user/player/with-role/role-name');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin

        $response = $this->runApp('GET', '/api/user/player/with-role/role-name');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWithRole400()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/with-role/invalid');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testWithRole200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('GET', '/api/user/player/with-role/'.Role::APP_ADMIN);
        $response2 = $this->runApp('GET', '/api/user/player/with-role/'.Role::APP_MANAGER);
        $response3 = $this->runApp('GET', '/api/user/player/with-role/'.Role::GROUP_ADMIN);
        $response4 = $this->runApp('GET', '/api/user/player/with-role/'.Role::GROUP_MANAGER);
        $response5 = $this->runApp('GET', '/api/user/player/with-role/'.Role::USER_ADMIN);
        $response6 = $this->runApp('GET', '/api/user/player/with-role/'.Role::USER_MANAGER);
        $response7 = $this->runApp('GET', '/api/user/player/with-role/'.Role::USER_CHARS);
        $response8 = $this->runApp('GET', '/api/user/player/with-role/'.Role::ESI);
        $response9 = $this->runApp('GET', '/api/user/player/with-role/'.Role::SETTINGS);
        $response10 = $this->runApp('GET', '/api/user/player/with-role/'.Role::TRACKING);
        $response11 = $this->runApp('GET', '/api/user/player/with-role/'.Role::TRACKING_ADMIN);
        $response12 = $this->runApp('GET', '/api/user/player/with-role/'.Role::WATCHLIST);
        $response13 = $this->runApp('GET', '/api/user/player/with-role/'.Role::WATCHLIST_MANAGER);
        $response14 = $this->runApp('GET', '/api/user/player/with-role/'.Role::WATCHLIST_ADMIN);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals(200, $response3->getStatusCode());
        $this->assertEquals(200, $response4->getStatusCode());
        $this->assertEquals(200, $response5->getStatusCode());
        $this->assertEquals(200, $response6->getStatusCode());
        $this->assertEquals(200, $response7->getStatusCode());
        $this->assertEquals(200, $response8->getStatusCode());
        $this->assertEquals(200, $response9->getStatusCode());
        $this->assertEquals(200, $response10->getStatusCode());
        $this->assertEquals(200, $response11->getStatusCode());
        $this->assertEquals(200, $response12->getStatusCode());
        $this->assertEquals(200, $response13->getStatusCode());
        $this->assertEquals(200, $response14->getStatusCode());

        $this->assertSame([
            ['id' => $this->player3Id, 'name' => 'Admin']
        ], $this->parseJsonBody($response1));
        $this->assertSame([
            ['id' => $this->managerId, 'name' => 'Manager']
        ], $this->parseJsonBody($response2));
        $this->assertSame([
            ['id' => $this->player3Id, 'name' => 'Admin']
        ], $this->parseJsonBody($response3));
        $this->assertSame([
            ['id' => $this->managerId, 'name' => 'Manager']
        ], $this->parseJsonBody($response4));
        $this->assertSame([
            ['id' => $this->player3Id, 'name' => 'Admin']
        ], $this->parseJsonBody($response5));
    }

    public function testWithStatus403()
    {
        $response = $this->runApp('GET', '/api/user/player/with-status/'.Player::STATUS_STANDARD);
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin

        $response = $this->runApp('GET', '/api/user/player/with-status/'.Player::STATUS_STANDARD);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWithStatus400()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/with-status/invalid');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testWithStatus200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/with-status/'.Player::STATUS_MANAGED);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [['id' => $this->managerId, 'name' => 'Manager']],
            $this->parseJsonBody($response)
        );
    }

    public function testAddRole403()
    {
        $response = $this->runApp('PUT', "/api/user/player/$this->noPlayerId/add-role/r");
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin or group-admin

        $response = $this->runApp('PUT', "/api/user/player/$this->noPlayerId/add-role/r");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddRole404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', "/api/user/player/$this->noPlayerId/add-role/r");
        $response2 = $this->runApp('PUT', "/api/user/player/$this->noPlayerId/add-role/".Role::APP_MANAGER);
        $response3 = $this->runApp('PUT', '/api/user/player/'.$this->player3Id.'/add-role/role');

        // app is a valid role, but not for users, app-manager is auto assigned
        $response4 = $this->runApp('PUT', '/api/user/player/'.$this->player3Id.'/add-role/'.Role::APP);
        $response5 = $this->runApp('PUT', '/api/user/player/'.$this->player3Id.'/add-role/'.Role::APP_MANAGER);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
        $this->assertEquals(404, $response5->getStatusCode());
    }

    public function testAddRole204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $r1 = $this->runApp('PUT', '/api/user/player/'.($this->player3Id).'/add-role/'.Role::USER_MANAGER);
        $r2 = $this->runApp('PUT', '/api/user/player/'.($this->player3Id).'/add-role/'.Role::USER_MANAGER);
        $this->assertEquals(204, $r1->getStatusCode());
        $this->assertEquals(204, $r2->getStatusCode());

        $this->em->clear();

        $player = $this->fetchPlayer($this->player3Id);
        $this->assertSame(
            [Role::APP_ADMIN, Role::GROUP_ADMIN, Role::USER, Role::USER_ADMIN, Role::USER_MANAGER],
            $player->getRoleNames()
        );
    }

    public function testRemoveRole403()
    {
        $response = $this->runApp('PUT', "/api/user/player/$this->noPlayerId/remove-role/r");
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin or group-admin

        $response = $this->runApp('PUT', "/api/user/player/$this->noPlayerId/remove-role/r");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveRole404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', "/api/user/player/$this->noPlayerId/remove-role/a");
        $response2 = $this->runApp('PUT', "/api/user/player/$this->noPlayerId/remove-role/".Role::APP_MANAGER);
        $response3 = $this->runApp('PUT', '/api/user/player/'.$this->player3Id.'/remove-role/a');

        // user is a valid role, but may not be removed, group-manager is auto assigned
        $response4 = $this->runApp('PUT', '/api/user/player/'.$this->player3Id.'/remove-role/'.Role::USER);
        $response5 = $this->runApp('PUT', '/api/user/player/'.$this->player3Id.'/remove-role/'.Role::GROUP_MANAGER);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
        $this->assertEquals(404, $response5->getStatusCode());
    }

    public function testRemoveRole500()
    {
        $this->setupDb();
        $this->loginUser(12);

        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $res = $this->runApp(
            'PUT',
            '/api/user/player/'.$this->player3Id.'/remove-role/'.Role::APP_ADMIN,
            null,
            null,
            [ObjectManager::class => $this->em, LoggerInterface::class => $this->log]
        );
        $this->assertEquals(500, $res->getStatusCode());
    }

    public function testRemoveRole204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $r1 = $this->runApp('PUT', '/api/user/player/'.$this->player3Id.'/remove-role/'.Role::APP_ADMIN);
        $r2 = $this->runApp('PUT', '/api/user/player/'.$this->player3Id.'/remove-role/'.Role::APP_ADMIN);
        $this->assertEquals(204, $r1->getStatusCode());
        $this->assertEquals(204, $r2->getStatusCode());

        $this->em->clear();

        $player = $this->fetchPlayer($this->player3Id);
        $this->assertSame(
            [Role::GROUP_ADMIN, Role::USER, Role::USER_ADMIN],
            $player->getRoleNames()
        );
    }

    public function testShowById403()
    {
        $response = $this->runApp('GET', "/api/user/player/$this->noPlayerId/show");
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(10); // not user-admin or group-manager

        $response = $this->runApp('GET', "/api/user/player/$this->noPlayerId/show");
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShowById404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/'.($this->player3Id + 5).'/show');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testShowById200()
    {
        $this->setupDb();
        $this->loginUser(12);

        // add service with account
        $conf = new PluginConfigurationDatabase();
        $conf->directoryName = 'plugin';
        $service = (new Plugin())->setName('A Service')->setConfigurationDatabase($conf);
        $this->em->persist($service);
        $this->em->flush();

        $response = $this->runApp(
            'GET',
            '/api/user/player/'.$this->player3Id.'/show',
            null,
            null,
            [],
            [['NEUCORE_PLUGINS_INSTALL_DIR', __DIR__ . '/PlayerController']],
        );
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $this->player3Id,
            'name' => 'Admin',
            'status' => Player::STATUS_STANDARD,
            'roles' => [Role::APP_ADMIN, Role::GROUP_ADMIN, Role::USER, Role::USER_ADMIN],
            'characters' => [[
                'id' => 12,
                'name' => 'Admin',
                'main' => true,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => false,
                'validTokenTime' => $this->date1,
                'tokenLastChecked' => null,
                'corporation' => [
                    'id' => 234, 'name' => 'ccc', 'ticker' => 'c-c', 'alliance' => [
                        'id' => 123, 'name' => 'aaa', 'ticker' => 'a-a'
                    ]
                ],
                'esiTokens' => [[
                    'eveLoginId' => $this->eveLoginId,
                    'characterId' => 12,
                    'playerId' => $this->player3Id,
                    'validToken' => false,
                    'validTokenTime' => $this->date1, // same as above (character.validTokenTime)
                    'hasRoles' => null,
                    'lastChecked' => null,
                ]],
                'characterNameChanges' => [['oldName' => 'old name', 'changeDate' => '2021-08-27T21:48:03Z']],
            ], [
                'id' => 13,
                'name' => 'Alt',
                'main' => false,
                'created' => null,
                'lastUpdate' => null,
                'validToken' => true,
                'validTokenTime' => '2019-08-03T23:12:46Z',
                'tokenLastChecked' => null,
                'corporation' => null,
                'esiTokens' => [[
                    'eveLoginId' => $this->eveLoginId,
                    'characterId' => 13,
                    'playerId' => $this->player3Id,
                    'validToken' => true,
                    'validTokenTime' => '2019-08-03T23:12:46Z',
                    'hasRoles' => null,
                    'lastChecked' => null,
                ]],
                'characterNameChanges' => [],
            ]],
            'groups' => [[
                'id' => $this->requiredGroupId,
                'name' => 'required-group',
                'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE,
                'autoAccept' => false,
                'isDefault' => false,
            ]],
            'managerGroups' => [],
            'managerApps' => [],
            'removedCharacters' => [],
            'incomingCharacters' => [],
            'serviceAccounts' => [
                [
                    'serviceId' => $service->getId(),
                    'serviceName' => $service->getName(),
                    'characterId' => 12,
                    'username' => 'user_name',
                    'status' => ServiceAccountData::STATUS_ACTIVE,
                    'name' => 'A Name',
                ]
            ],
        ], $this->parseJsonBody($response));
    }

    public function testCharacters403()
    {
        $this->setupDb();
        $this->loginUser(15); // does not have any of the required roles

        $response0 = $this->runApp('GET', '/api/user/player/'.$this->player3Id.'/characters');
        $this->assertEquals(403, $response0->getStatusCode());
    }

    public function testCharacters_Tracking_403()
    {
        $this->setupDb();
        $this->loginUser(11); // tracking role but missing group, not watchlist role

        $response = $this->runApp('GET', '/api/user/player/'.$this->player3Id.'/characters');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCharacters_Watchlist_403()
    {
        $this->setupDb();
        $this->h->addCharacterMain('Watchlist', 1011, [Role::USER, Role::WATCHLIST]);
        $this->loginUser(1011);

        $response2 = $this->runApp('GET', '/api/user/player/'.$this->player3Id.'/characters');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testCharacters404()
    {
        $this->setupDb();
        $this->loginUser(12); // user-admin

        $response = $this->runApp('GET', '/api/user/player/'.($this->player3Id + 5).'/characters');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCharacters200()
    {
        $this->setupDb();

        $this->loginUser(10); // user-chars
        $response1 = $this->runApp('GET', '/api/user/player/'.$this->player3Id.'/characters');
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertSame([
            'id' => $this->player3Id,
            'name' => 'Admin',
            'characters' => [
                [
                    'id' => 12,
                    'name' => 'Admin',
                    'main' => true,
                    'created' => null,
                    'lastUpdate' => null,
                    'validToken' => false,
                    'validTokenTime' => $this->date1,
                    'tokenLastChecked' => null,
                    'corporation' => [
                        'id' => 234, 'name' => 'ccc', 'ticker' => 'c-c', 'alliance' => [
                            'id' => 123, 'name' => 'aaa', 'ticker' => 'a-a'
                        ]
                    ],
                    'characterNameChanges' => [['oldName' => 'old name', 'changeDate' => '2021-08-27T21:48:03Z']],
                ],
                [
                    'id' => 13,
                    'name' => 'Alt',
                    'main' => false,
                    'created' => null,
                    'lastUpdate' => null,
                    'validToken' => true,
                    'validTokenTime' => '2019-08-03T23:12:46Z',
                    'tokenLastChecked' => null,
                    'corporation' => null,
                    'characterNameChanges' => [],
                ],
            ],
            'groups' => [[
                'id' => $this->requiredGroupId,
                'name' => 'required-group',
                'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE,
                'autoAccept' => false,
                'isDefault' => false,
            ]],
            'removedCharacters' => [],
            'incomingCharacters' => [],
            'serviceAccounts' => [],
        ], $this->parseJsonBody($response1));
    }

    public function testCharacters_Tracking_200()
    {
        $this->setupDb();
        $this->loginUser(11);

        $this->corpRepo->find($this->corpId)->addGroupTracking($this->fetchGroup($this->groupId));
        $this->em->flush();

        $response = $this->runApp('GET', '/api/user/player/'.$this->player3Id.'/characters');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCharacters_Watchlist_200()
    {
        $this->setupDb();
        $corpOther = (new Corporation())->setId(100500600)->setName('c2')->setTicker('c-2');
        $playerWatch = $this->playerRepo->find($this->player3Id);
        $playerWatch->getCharacters()[1]->setCorporation($corpOther);
        $corpWatch = $playerWatch->getCharacters()[0]->getCorporation();
        $watchlist = (new Watchlist())->setName('wl1');
        if ($corpWatch) {
            $watchlist->addCorporation($corpWatch);
        }
        $this->em->persist($watchlist);
        $this->em->persist($corpOther);
        $user = $this->h->addCharacterMain('Watchlist', 1011, [Role::USER, Role::WATCHLIST])->getPlayer();
        $group = $this->groupRepo->find($this->gPrivateId);
        if ($group) {
            $watchlist->addGroup($group);
            $user->addGroup($group);
        }
        $this->em->flush();
        $this->loginUser(1011);

        $response2 = $this->runApp('GET', '/api/user/player/'.$this->player3Id.'/characters');
        $this->assertEquals(200, $response2->getStatusCode());
    }

    public function testGroupCharactersByAccount403()
    {
        $this->setupDb();

        $response1 = $this->runApp('POST', '/api/user/player/group-characters-by-account');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(11);

        $response2 = $this->runApp('POST', '/api/user/player/group-characters-by-account');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testGroupCharactersByAccount200()
    {
        $this->setupDb();
        $this->loginUser(10);

        $response = $this->runApp(
            'POST',
            '/api/user/player/group-characters-by-account',
            "User\nManager\nInvalid\nAlt1\n\nAccount with no main"
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
            ['player_id' => $this->player1Id, 'characters' => [
                ['id' => 10, 'name' => 'User'],
                ['id' => 9, 'name' => 'Alt1']]
            ],
            ['player_id' => $this->managerId, 'characters' => [
                ['id' => 11, 'name' => 'Manager']
            ]],
            ['player_id' => $this->player5, 'characters' => [
                ['id' => 0, 'name' => '[no main]'],
                ['id' => 15, 'name' => 'Account with no main'],
            ]],
            ['player_id' => null, 'characters' => [
                ['id' => 0, 'name' => 'Invalid']
            ]],
        ], $this->parseJsonBody($response));
    }

    public function testDeleteCharacter404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('DELETE', '/api/user/player/delete-character/50');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteCharacter403()
    {
        $this->setupDb();
        $this->loginUser(11); // not a user-admin

        // char 10 is on a different player account

        $response = $this->runApp('DELETE', '/api/user/player/delete-character/10');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDeleteCharacter403Disabled()
    {
        $this->setupDb();

        // deactivate deletion feature
        $setting = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $setting->setValue('0');
        $this->em->persist($setting);
        $this->em->flush();

        $this->loginUser(12);

        $response = $this->runApp('DELETE', '/api/user/player/delete-character/13');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDeleteCharacter409()
    {
        $this->setupDb();
        $this->loginUser(12);

        // cannot delete logged in char

        $response = $this->runApp('DELETE', '/api/user/player/delete-character/12');
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testDeleteCharacter204()
    {
        $this->setupDb();
        $this->loginUser(10);

        $response = $this->runApp('DELETE', '/api/user/player/delete-character/9');
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $deleted = $this->charRepo->find(9);
        $this->assertNull($deleted);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 9]);
        $this->assertSame(9, $removedChar->getCharacterId());
        $this->assertSame($this->player1Id, $removedChar->getPlayer()->getId());
        $this->assertNull($removedChar->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_DELETED_MANUALLY, $removedChar->getReason());
    }

    public function testDeleteCharacter403_Admin_NoReason_OwnCharacter_Deactivated()
    {
        $this->setupDb();
        $this->loginUser(12);

        // deactivate deletion feature
        $setting = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $setting->setValue('0');
        $this->em->persist($setting);
        $this->em->flush();

        $response = $this->runApp('DELETE', '/api/user/player/delete-character/13');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDeleteCharacter204_Admin_NoReason_OwnCharacter()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('DELETE', '/api/user/player/delete-character/13');
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $deleted = $this->charRepo->find(13);
        $this->assertNull($deleted);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 13]);
        $this->assertSame(13, $removedChar->getCharacterId());
        $this->assertSame($this->player3Id, $removedChar->getPlayer()->getId());
        $this->assertNull($removedChar->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_DELETED_MANUALLY, $removedChar->getReason());
    }

    public function testDeleteCharacter204_Admin_WithReason_OwnCharacter()
    {
        $this->setupDb();
        $this->loginUser(12); // a user-admin

        // deactivate deletion feature
        $setting = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $setting->setValue('0');
        $this->em->persist($setting);
        $this->em->flush();

        $response = $this->runApp(
            'DELETE',
            '/api/user/player/delete-character/13?admin-reason=' . RemovedCharacter::REASON_DELETED_OWNER_CHANGED
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $this->assertNull($this->charRepo->find(13));
        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 13]);
        $this->assertSame(RemovedCharacter::REASON_DELETED_OWNER_CHANGED, $removedChar->getReason());
    }

    public function testDeleteCharacter204_Admin_WithoutRecord()
    {
        $this->setupDb();
        $this->loginUser(12); // a user-admin

        // deactivate deletion feature
        $setting = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $setting->setValue('0');
        $this->em->persist($setting);
        $this->em->flush();

        // char 10 is on a different player account
        $response = $this->runApp(
            'DELETE',
            '/api/user/player/delete-character/10?admin-reason=' . RemovedCharacter::REASON_DELETED_BY_ADMIN,
            null,
            null,
            [LoggerInterface::class => $this->log]
        );
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $this->assertNull($this->charRepo->find(10));
        $this->assertNull($this->removedCharRepo->findOneBy(['characterId' => 10]));
        $this->assertSame(
            'An admin (player ID: ' . $this->player3Id . ') ' .
                'deleted character "User" [10] from player "User" [' . $this->player1Id . ']',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testDeleteCharacter204_Admin_WithRecord_UpdatesGroups()
    {
        $this->setupDb();
        $this->loginUser(12); // a user-admin

        // deactivate deletion feature
        $setting = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $setting->setValue('0');
        $this->em->persist($setting);
        $this->em->flush();
        $this->em->clear();

        $this->assertSame([$this->autoAssignGroupId], $this->playerRepo->find($this->player1Id)->getGroupIds());

        // char 10 is on a different player account
        $response = $this->runApp(
            'DELETE',
            '/api/user/player/delete-character/10?admin-reason=' . RemovedCharacter::REASON_DELETED_OWNER_CHANGED,
            null,
            null,
            [LoggerInterface::class => $this->log]
        );
        $this->em->clear();

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull($this->charRepo->find(10));

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 10]);
        $this->assertSame(10, $removedChar->getCharacterId());
        $this->assertSame($this->player1Id, $removedChar->getPlayer()->getId());
        $this->assertNull($removedChar->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_DELETED_OWNER_CHANGED, $removedChar->getReason());
        $this->assertSame($this->player3Id, $removedChar->getDeletedBy()->getId());

        $this->assertSame([], $this->playerRepo->find($this->player1Id)->getGroupIds());
    }

    public function testDeleteCharacter204_Admin_LostAccess()
    {
        $this->setupDb();
        $this->loginUser(12); // a user-admin

        // deactivate deletion feature
        $setting = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $setting->setValue('0');
        $this->em->persist($setting);
        $this->em->flush();

        // char 10 is on a different player account
        $response = $this->runApp(
            'DELETE',
            '/api/user/player/delete-character/10?admin-reason=' . RemovedCharacter::REASON_DELETED_LOST_ACCESS,
            [LoggerInterface::class => $this->log]
        );
        $this->em->clear();

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull($this->charRepo->find(10));
        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 10]);
        $this->assertSame(RemovedCharacter::REASON_DELETED_LOST_ACCESS, $removedChar->getReason());
    }

    public function testDeleteCharacter403_Admin_InvalidReason()
    {
        $this->setupDb();
        $this->loginUser(12); // a user-admin

        // deactivate deletion feature
        $setting = new SystemVariable(SystemVariable::ALLOW_CHARACTER_DELETION);
        $setting->setValue('0');
        $this->em->persist($setting);
        $this->em->flush();

        // char 10 is on a different player account
        $response = $this->runApp(
            'DELETE',
            '/api/user/player/delete-character/10?admin-reason=' . RemovedCharacter::REASON_DELETED_BIOMASSED,
            [LoggerInterface::class => $this->log]
        );
        $this->assertEquals(403, $response->getStatusCode());

        $this->em->clear();

        $this->assertNotNull($this->charRepo->find(10));
    }

    public function testGroupsDisabled403()
    {
        $response = $this->runApp('GET', '/api/user/player/groups-disabled');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupsDisabled200True()
    {
        $this->setupDb();
        $this->loginUser(12);

        // activate feature
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('234');
        $this->em->persist($setting);
        $this->em->persist($setting2);
        $this->em->persist($setting3);
        $this->em->flush();

        $response = $this->runApp('GET', '/api/user/player/groups-disabled');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(['withDelay' => true, 'withoutDelay' => true], $this->parseJsonBody($response));
    }

    public function testGroupsDisabled200Managed()
    {
        $this->setupDb();
        $this->loginUser(12);

        $this->fetchPlayer($this->player3Id)->setStatus(Player::STATUS_MANAGED);

        // activate feature
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->em->persist($setting);
        $this->em->flush();

        $response = $this->runApp('GET', '/api/user/player/groups-disabled');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(['withDelay' => false, 'withoutDelay' => false], $this->parseJsonBody($response));
    }

    public function testGroupsDisabled200False()
    {
        $this->setupDb();
        $this->loginUser(14);

        // activate feature
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->em->persist($setting);
        $this->em->flush();

        $response = $this->runApp('GET', '/api/user/player/groups-disabled');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(['withDelay' => false, 'withoutDelay' => false], $this->parseJsonBody($response));
    }

    public function testGroupsDisabledById403()
    {
        $response1 = $this->runApp('GET', "/api/user/player/$this->noPlayerId/groups-disabled");
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // tracking but no permission

        $response2 = $this->runApp('GET', "/api/user/player/$this->player1Id/groups-disabled");
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testGroupsDisabledById404()
    {
        $this->setupDb();
        $this->loginUser(12); // user-admin

        $response = $this->runApp('GET', '/api/user/player/' . ($this->player4Id + 99) . '/groups-disabled');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGroupsDisabledById200()
    {
        $this->setupDb();
        $this->loginUser(12); // user-admin

        // activate feature
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $setting2 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES))->setValue('');
        $setting3 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS))->setValue('234');
        /** @noinspection PhpUnhandledExceptionInspection */
        $dateDiff = (new \DateTime($this->date1))->diff(new \DateTime());
        $setting4 = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY))
            ->setValue((string)(($dateDiff->days * 24) + 24));
        $this->em->persist($setting);
        $this->em->persist($setting2);
        $this->em->persist($setting3);
        $this->em->persist($setting4);
        $this->em->flush();

        $response1 = $this->runApp('GET', '/api/user/player/' . $this->player3Id . '/groups-disabled');
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertSame(['withDelay' => false, 'withoutDelay' => true], $this->parseJsonBody($response1));

        $response2 = $this->runApp('GET', '/api/user/player/' . $this->player4Id . '/groups-disabled');
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertSame(['withDelay' => false, 'withoutDelay' => false], $this->parseJsonBody($response2));
    }

    private function setupDb(): void
    {
        $this->h->emptyDb();

        $this->h->addRoles([
            Role::USER,
            Role::APP_ADMIN,
            Role::APP_MANAGER,
            Role::GROUP_ADMIN,
            Role::GROUP_MANAGER,
            Role::USER_ADMIN,
            Role::USER_MANAGER,
            Role::USER_CHARS,
            Role::ESI,
            Role::SETTINGS,
            Role::TRACKING,
            Role::TRACKING_ADMIN,
            Role::WATCHLIST,
            Role::WATCHLIST_MANAGER,
            Role::WATCHLIST_ADMIN,
        ]);

        $gs = $this->h->addGroups(['test-pub', 'test-private', 'required-group', 'auto-accept', 'auto-assign']);
        $gs[0]->setVisibility(Group::VISIBILITY_PUBLIC);
        $gs[3]->setVisibility(Group::VISIBILITY_PUBLIC);
        $gs[3]->setAutoAccept(true);
        $gs[0]->addRequiredGroup($gs[2]);
        $this->groupId = $gs[0]->getId();
        $this->gPrivateId = $gs[1]->getId();
        $this->requiredGroupId = $gs[2]->getId();
        $this->autoAcceptGroupId = $gs[3]->getId();
        $this->autoAssignGroupId = $gs[4]->getId();

        $corp1 = (new Corporation())->setId(123)->setName('c1')->setTicker('c1');
        $this->em->persist($corp1);
        $char1 = $this->h->addCharacterMain('User', 10, [Role::USER, Role::USER_CHARS]);
        $char1->setCorporation($corp1);
        $corp1->addGroup($gs[4]);
        $player1 = $char1->getPlayer();
        $player1->addGroup($gs[4]);
        $this->player1Id = $player1->getId();
        $this->eveLoginId = $char1->getEsiTokens()[0]->getEveLogin()->getId();
        $this->h->addCharacterToPlayer('Alt1', 9, $player1);

        $player2 = $this->h->addCharacterMain(
            'Manager',
            11,
            [Role::USER, Role::APP_MANAGER, Role::GROUP_MANAGER, Role::TRACKING]
        )->getPlayer();
        $this->managerId = $player2->getId();
        $player2->setStatus(Player::STATUS_MANAGED);
        $player2->addGroup($gs[0]);

        $alli = (new Alliance())->setId(123)->setName('aaa')->setTicker('a-a');
        $corp2 = (new Corporation())->setId(234)->setName('ccc')->setTicker('c-c')->setAlliance($alli);
        $this->corpId = $corp2->getId();

        $char3a = $this->h->addCharacterMain(
            'Admin',
            12,
            [Role::USER, Role::APP_ADMIN, Role::USER_ADMIN, Role::GROUP_ADMIN]
        );
        /** @noinspection PhpUnhandledExceptionInspection */
        $char3a->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(false)
            ->setValidTokenTime(new \DateTime($this->date1));
        $char3a->setCorporation($corp2);
        $char3a->getPlayer()->addGroup($gs[2]);
        $this->player3Id = $char3a->getPlayer()->getId();

        $charNameChange = (new CharacterNameChange())->setOldName('old name')
            ->setChangeDate(new \DateTime('2021-08-27 21:48:03'));
        $charNameChange->setCharacter($char3a);
        $this->em->persist($charNameChange);

        $emptyAcc = (new Player())->setName('empty account');

        $this->em->persist($corp2);
        $this->em->persist($alli);
        $this->em->persist($emptyAcc);

        $char3b = $this->h->addCharacterToPlayer('Alt', 13, $char3a->getPlayer(), true);
        $char3b->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(true)
            ->setValidTokenTime(new \DateTime('2019-08-03T23:12:46Z'));

        $char4 = $this->h->addCharacterMain('User3', 14, [Role::USER, Role::USER_MANAGER]);
        $char4->getEsiToken(EveLogin::NAME_DEFAULT)->setValidToken(true);
        $this->player4Id = $char4->getPlayer()->getId();

        $charNoMain = $this->h->addCharacterMain('Account with no main', 15);
        $charNoMain->setMain(false);
        $this->player5 = $charNoMain->getPlayer()->getId();

        $this->noPlayerId = $this->player5 + 99;

        $this->em->flush();
        $this->em->clear();
        $this->emptyAccId = $emptyAcc->getId();
    }

    private function fetchPlayer(int $id): Player
    {
        return $this->playerRepo->find($id);
    }

    private function fetchGroup(int $id): Group
    {
        return $this->groupRepo->find($id);
    }
}
