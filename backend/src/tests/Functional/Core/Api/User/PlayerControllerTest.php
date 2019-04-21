<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\GroupApplication;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\RemovedCharacter;
use Brave\Core\Entity\Role;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Repository\CharacterRepository;
use Brave\Core\Repository\GroupApplicationRepository;
use Brave\Core\Repository\PlayerRepository;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\RemovedCharacterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class PlayerControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $h;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    private $userId;

    private $player3Id;

    private $managerId;

    private $emptyAccId;

    /**
     * @var Player
     */
    private $player;

    /**
     * @var Group
     */
    private $group;

    private $gPrivateId;

    /**
     * @var PlayerRepository
     */
    private $playerRepo;

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var RemovedCharacterRepository
     */
    private $removedCharRepo;

    /**
     * @var GroupApplicationRepository
     */
    private $groupAppRepo;

    /**
     * @var Logger
     */
    private $log;

    public function setUp()
    {
        $_SESSION = null;

        $this->h = new Helper();
        $this->em = $this->h->getEm();

        $rf = new RepositoryFactory($this->em);
        $this->playerRepo = $rf->getPlayerRepository();
        $this->charRepo = $rf->getCharacterRepository();
        $this->removedCharRepo = $rf->getRemovedCharacterRepository();
        $this->groupAppRepo = $rf->getGroupApplicationRepository();

        $this->log = new Logger('test');
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
        $this->h->getEm()->persist($alli);
        $this->h->getEm()->persist($corp);
        $this->h->getEm()->flush();
        $this->loginUser(123456);

        $response = $this->runApp('GET', '/api/user/player/show');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $char->getPlayer()->getId(),
            'name' => 'TUser',
            'status' => Player::STATUS_STANDARD,
            'roles' => [Role::USER, Role::USER_ADMIN],
            'characters' => [
                [
                    'id' => 123456,
                    'name' => 'TUser',
                    'main' => true,
                    'lastUpdate' => null,
                    'validToken' => null,
                    'corporation' => [
                        'id' => 456, 'name' => 'corp1', 'ticker' => 'MT', 'alliance' => [
                            'id' => 123, 'name' => 'alli1', 'ticker' => 'ATT'
                        ]
                    ]
                ],
            ],
            'groups' => [
                ['id' => $groups[1]->getId(), 'name' => 'another-group', 'visibility' => Group::VISIBILITY_PRIVATE],
                ['id' => $groups[0]->getId(), 'name' => 'group1', 'visibility' => Group::VISIBILITY_PRIVATE]
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

        $response = $this->runApp('PUT', '/api/user/player/add-application/' . ($this->group->getId() + 5));
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->runApp('PUT', '/api/user/player/add-application/' . $this->gPrivateId);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAddApplication204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/player/add-application/'. $this->group->getId());
        $response2 = $this->runApp('PUT', '/api/user/player/add-application/'. $this->group->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();
        $groupApps = $this->groupAppRepo->findBy([]);
        $this->assertSame(1, count($groupApps));
        $this->assertSame($this->group->getId(), $groupApps[0]->getGroup()->getId());
        $this->assertSame($this->player->getId(), $groupApps[0]->getPlayer()->getId());
        $this->assertLessThanOrEqual(time(), $groupApps[0]->getCreated()->getTimestamp());
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

        $response = $this->runApp('PUT', '/api/user/player/remove-application/' . ($this->group->getId() + 5));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRemoveApplication204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $ga = new GroupApplication();
        $ga->setGroup($this->group);
        $ga->setPlayer($this->player);
        $this->em->persist($ga);
        $this->em->flush();

        $response = $this->runApp('PUT', '/api/user/player/remove-application/' . $this->group->getId());
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
        $ga->setGroup($this->group);
        $ga->setPlayer($this->player);
        $this->em->persist($ga);
        $this->em->flush();

        $response = $this->runApp('GET', '/api/user/player/show-applications');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([[
            'id' => $ga->getId(),
            'player' => ['id' => $this->player->getId(), 'name' => 'Admin'],
            'group' => ['id' => $this->group->getId(), 'name' => 'test-pub', 'visibility' => Group::VISIBILITY_PUBLIC],
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

        $response = $this->runApp('PUT', '/api/user/player/leave-group/' . ($this->group->getId() + 5));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testLeaveGroup204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $this->player->addGroup($this->group);
        $this->em->flush();

        $response = $this->runApp('PUT', '/api/user/player/leave-group/' . $this->group->getId());
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();
        $p = $this->playerRepo->find($this->player->getId());
        $this->assertSame(0, count($p->getGroups()));
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

        $charsBefore = $this->playerRepo->find($this->player->getId())->getCharacters();
        $this->assertSame(12, $charsBefore[0]->getId());
        $this->assertSame(13, $charsBefore[1]->getId());
        $this->assertTrue($charsBefore[0]->getMain());
        $this->assertFalse($charsBefore[1]->getMain());
        $this->assertSame('Alt', $charsBefore[1]->getName());
        $this->assertSame('Admin', $this->player->getName());

        $response = $this->runApp('PUT', '/api/user/player/set-main/13');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            ['id' => 13,
                'name' => 'Alt',
                'main' => true,
                'lastUpdate' => null,
                'validToken' => true,
                'corporation' => null
            ],
            $this->parseJsonBody($response)
        );

        $this->em->clear();

        $playerAfter = $this->playerRepo->find($this->player->getId());
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

        $response1 = $this->runApp('PUT', '/api/user/player/'.$this->managerId.'/set-status/managed');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(11); // not a user-admin

        $response2 = $this->runApp('PUT', '/api/user/player/'.$this->managerId.'/set-status/managed');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testSetStatus400()
    {
        $this->setupDb();
        $this->loginUser(12); // has role user-admin

        $response1 = $this->runApp('PUT', '/api/user/player/'.$this->managerId.'/set-status/invalid');
        $this->assertEquals(400, $response1->getStatusCode());

        $response2 = $this->runApp('PUT', '/api/user/player/99/set-status/managed');
        $this->assertEquals(400, $response2->getStatusCode());
    }

    public function testSetStatus204()
    {
        $this->setupDb();
        $this->loginUser(12); // has role user-admin

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
            ['id' => $this->player->getId(), 'name' => 'Admin'],
            ['id' => $this->managerId, 'name' => 'Manager'],
            ['id' => $this->userId, 'name' => 'User'],
            ['id' => $this->player3Id, 'name' => 'User3'],
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

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals(200, $response3->getStatusCode());
        $this->assertEquals(200, $response4->getStatusCode());
        $this->assertEquals(200, $response5->getStatusCode());

        $this->assertSame([
            ['id' => $this->player->getId(), 'name' => 'Admin']
        ], $this->parseJsonBody($response1));
        $this->assertSame([
            ['id' => $this->managerId, 'name' => 'Manager']
        ], $this->parseJsonBody($response2));
        $this->assertSame([
            ['id' => $this->player->getId(), 'name' => 'Admin']
        ], $this->parseJsonBody($response3));
        $this->assertSame([
            ['id' => $this->managerId, 'name' => 'Manager']
        ], $this->parseJsonBody($response4));
        $this->assertSame([
            ['id' => $this->player->getId(), 'name' => 'Admin']
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
        $response = $this->runApp('PUT', '/api/user/player/101/add-role/r');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin or group-admin

        $response = $this->runApp('PUT', '/api/user/player/101/add-role/r');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddRole404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/player/101/add-role/r');
        $response2 = $this->runApp('PUT', '/api/user/player/101/add-role/'.Role::APP_MANAGER);
        $response3 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/add-role/role');

        // app is a valid role, but not for users
        $response4 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/add-role/'.Role::APP);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
    }

    public function testAddRole204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $r1 = $this->runApp('PUT', '/api/user/player/'.($this->player->getId()).'/add-role/'.Role::APP_MANAGER);
        $r2 = $this->runApp('PUT', '/api/user/player/'.($this->player->getId()).'/add-role/'.Role::APP_MANAGER);
        $this->assertEquals(204, $r1->getStatusCode());
        $this->assertEquals(204, $r2->getStatusCode());

        $this->em->clear();

        $player = $this->playerRepo->find($this->player->getId());
        $this->assertSame(
            [Role::APP_ADMIN, Role::APP_MANAGER, Role::GROUP_ADMIN, Role::USER, Role::USER_ADMIN],
            $player->getRoleNames()
        );
    }

    public function testRemoveRole403()
    {
        $response = $this->runApp('PUT', '/api/user/player/101/remove-role/r');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(11); // not user-admin or group-admin

        $response = $this->runApp('PUT', '/api/user/player/101/remove-role/r');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveRole404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/player/101/remove-role/a');
        $response2 = $this->runApp('PUT', '/api/user/player/101/remove-role/'.Role::APP_MANAGER);
        $response3 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/remove-role/a');

        // user is a valid role, but may not be removed
        $response4 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/remove-role/'.Role::USER);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
    }

    public function testRemoveRole500()
    {
        $this->setupDb();
        $this->loginUser(12);

        $em = $this->h->getEm(true);
        $em->getEventManager()->addEventListener(Events::onFlush, new WriteErrorListener());

        $res = $this->runApp(
            'PUT',
            '/api/user/player/'.$this->player->getId().'/remove-role/'.Role::APP_ADMIN,
            null,
            null,
            [
                EntityManagerInterface::class => $em,
                LoggerInterface::class => $this->log
            ]
        );
        $this->assertEquals(500, $res->getStatusCode());
    }

    public function testRemoveRole204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $r1 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/remove-role/'.Role::APP_ADMIN);
        $r2 = $this->runApp('PUT', '/api/user/player/'.$this->player->getId().'/remove-role/'.Role::APP_ADMIN);
        $this->assertEquals(204, $r1->getStatusCode());
        $this->assertEquals(204, $r2->getStatusCode());

        $this->em->clear();

        $player = $this->playerRepo->find($this->player->getId());
        $this->assertSame(
            [Role::GROUP_ADMIN, Role::USER, Role::USER_ADMIN],
            $player->getRoleNames()
        );
    }

    public function testShowById403()
    {
        $response = $this->runApp('GET', '/api/user/player/1/show');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(10); // not user-admin or group-manager

        $response = $this->runApp('GET', '/api/user/player/1/show');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShowById404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/'.($this->player->getId() + 5).'/show');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testShowById200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/'.$this->player->getId().'/show');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $this->player->getId(),
            'name' => 'Admin',
            'status' => Player::STATUS_STANDARD,
            'roles' => [Role::APP_ADMIN, Role::GROUP_ADMIN, Role::USER, Role::USER_ADMIN],
            'characters' => [
                [
                    'id' => 12,
                    'name' => 'Admin',
                    'main' => true,
                    'lastUpdate' => null,
                    'validToken' => false,
                    'corporation' => [
                        'id' => 234, 'name' => 'ccc', 'ticker' => 'c-c', 'alliance' => [
                            'id' => 123, 'name' => 'aaa', 'ticker' => 'a-a'
                        ]
                    ]
                ],
                [
                    'id' => 13,
                    'name' => 'Alt',
                    'main' => false,
                    'lastUpdate' => null,
                    'validToken' => true,
                    'corporation' => null
                ],
            ],
            'groups' => [],
            'managerGroups' => [],
            'managerApps' => [],
            'removedCharacters' => [],
        ], $this->parseJsonBody($response));
    }

    public function testCharacters403()
    {
        $this->setupDb();
        $this->loginUser(10); // not a group-manager, group-admin or app-admin

        $response = $this->runApp('GET', '/api/user/player/'.$this->player->getId().'/characters');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCharacters404()
    {
        $this->setupDb();
        $this->loginUser(11);

        $response = $this->runApp('GET', '/api/user/player/'.($this->player->getId() + 5).'/characters');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCharacters200()
    {
        $this->setupDb();
        $this->loginUser(11);

        $response = $this->runApp('GET', '/api/user/player/'.$this->player->getId().'/characters');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $this->player->getId(),
            'name' => 'Admin',
            'characters' => [
                [
                    'id' => 12,
                    'name' => 'Admin',
                    'main' => true,
                    'lastUpdate' => null,
                    'validToken' => false,
                    'corporation' => [
                        'id' => 234, 'name' => 'ccc', 'ticker' => 'c-c', 'alliance' => [
                            'id' => 123, 'name' => 'aaa', 'ticker' => 'a-a'
                        ]
                    ]
                ],
                [
                    'id' => 13,
                    'name' => 'Alt',
                    'main' => false,
                    'lastUpdate' => null,
                    'validToken' => true,
                    'corporation' => null
                ],
            ],
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
        $this->loginUser(12);

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
        $this->h->getEm()->persist($setting);
        $this->h->getEm()->flush();

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
        $this->loginUser(12);

        $response = $this->runApp('DELETE', '/api/user/player/delete-character/13');
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $deleted = $this->charRepo->find(13);
        $this->assertNull($deleted);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 13]);
        $this->assertSame(13, $removedChar->getCharacterId());
        $this->assertSame($this->player->getId(), $removedChar->getPlayer()->getId());
        $this->assertNull($removedChar->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_DELETED_MANUALLY, $removedChar->getReason());
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
        $this->h->getEm()->persist($setting);
        $this->h->getEm()->flush();

        $response = $this->runApp('GET', '/api/user/player/groups-disabled');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertTrue($this->parseJsonBody($response));
    }

    public function testGroupsDisabled200Managed()
    {
        $this->setupDb();
        $this->loginUser(12);

        $this->player->setStatus(Player::STATUS_MANAGED);

        // activate feature
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->h->getEm()->persist($setting);
        $this->h->getEm()->flush();

        $response = $this->runApp('GET', '/api/user/player/groups-disabled');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertFalse($this->parseJsonBody($response));
    }

    public function testGroupsDisabled200False()
    {
        $this->setupDb();
        $this->loginUser(14);

        // activate feature
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->h->getEm()->persist($setting);
        $this->h->getEm()->flush();

        $response = $this->runApp('GET', '/api/user/player/groups-disabled');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertFalse($this->parseJsonBody($response));
    }

    public function testGroupsDisabledById403()
    {
        $response1 = $this->runApp('GET', '/api/user/player/1/groups-disabled');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setupDb();
        $this->loginUser(14); // no user-admin

        $response2 = $this->runApp('GET', '/api/user/player/1/groups-disabled');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testGroupsDisabledById404()
    {
        $this->setupDb();
        $this->loginUser(12); // user-admin

        $response = $this->runApp('GET', '/api/user/player/' . ($this->player3Id + 99) . '/groups-disabled');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGroupsDisabledById200()
    {
        $this->setupDb();
        $this->loginUser(12); // user-admin

        // activate feature
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->h->getEm()->persist($setting);
        $this->h->getEm()->flush();

        $response1 = $this->runApp('GET', '/api/user/player/' . $this->player->getId() . '/groups-disabled');
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertTrue($this->parseJsonBody($response1));

        $response2 = $this->runApp('GET', '/api/user/player/' . $this->player3Id . '/groups-disabled');
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertFalse($this->parseJsonBody($response2));
    }

    private function setupDb()
    {
        $this->h->emptyDb();

        $this->h->addRoles([
            Role::USER,
            Role::APP_ADMIN,
            Role::APP_MANAGER,
            Role::GROUP_ADMIN,
            Role::GROUP_MANAGER,
            Role::USER_ADMIN
        ]);

        $gs = $this->h->addGroups(['test-pub', 'test-private']);
        $gs[0]->setVisibility(Group::VISIBILITY_PUBLIC);
        $this->group = $gs[0];
        $this->gPrivateId = $gs[1]->getId();

        $this->userId = $this->h->addCharacterMain('User', 10, [Role::USER])->getPlayer()->getId();

        $player = $this->managerId = $this->h->addCharacterMain(
            'Manager',
            11,
            [Role::USER, Role::APP_MANAGER, Role::GROUP_MANAGER]
        )->getPlayer();
        $this->managerId = $player->getId();
        $player->setStatus(Player::STATUS_MANAGED);
        $player->addGroup($gs[0]);

        $alli = (new Alliance())->setId(123)->setName('aaa')->setTicker('a-a');
        $corp = (new Corporation())->setId(234)->setName('ccc')->setTicker('c-c')->setAlliance($alli);

        $char = $this->h->addCharacterMain(
            'Admin',
            12,
            [Role::USER, Role::APP_ADMIN, Role::USER_ADMIN, Role::GROUP_ADMIN]
        );
        $char->setValidToken(false);
        $char->setCorporation($corp);
        $this->player = $char->getPlayer();

        $emptyAcc = (new Player())->setName('empty account');

        $this->h->getEm()->persist($corp);
        $this->h->getEm()->persist($alli);
        $this->h->getEm()->persist($emptyAcc);

        $char2 = $this->h->addCharacterToPlayer('Alt', 13, $this->player);
        $char2->setValidToken(true);

        $this->player3Id = $this->h->addCharacterMain('User3', 14, [Role::USER])
            ->setValidToken(true)->getPlayer()->getId();

        $this->h->getEm()->flush();
        $this->emptyAccId = $emptyAcc->getId();
    }
}
