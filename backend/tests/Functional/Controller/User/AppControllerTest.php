<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\GroupRepository;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Repository\AppRepository;
use Neucore\Entity\App;
use Doctrine\ORM\Events;
use Monolog\Handler\TestHandler;
use Neucore\Repository\PlayerRepository;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class AppControllerTest extends WebTestCase
{
    /**
     * @var WriteErrorListener
     */
    private static $writeErrorListener;

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var AppRepository
     */
    private $appRepo;

    /**
     * @var GroupRepository
     */
    private $groupRepo;

    /**
     * @var PlayerRepository
     */
    private $playerRepo;

    private $gid;

    private $aid;

    private $pid3;

    public static function setupBeforeClass(): void
    {
        self::$writeErrorListener = new WriteErrorListener();
    }

    protected function setUp(): void
    {
        $_SESSION = null;

        $this->helper = new Helper();
        $this->em = $this->helper->getEm();

        $repositoryFactory = new RepositoryFactory($this->em);
        $this->appRepo = $repositoryFactory->getAppRepository();
        $this->groupRepo = $repositoryFactory->getGroupRepository();
        $this->playerRepo = $repositoryFactory->getPlayerRepository();
    }

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testAll403()
    {
        $response = $this->runApp('GET', '/api/user/app/all');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('GET', '/api/user/app/all');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAll200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/app/all');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->aid, 'name' => 'app one']],
            $this->parseJsonBody($response)
        );
    }

    public function testCreate403()
    {
        $response = $this->runApp('POST', '/api/user/app/create');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('POST', '/api/user/app/create');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCreate400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('POST', '/api/user/app/create');
        $this->assertEquals(400, $response1->getStatusCode());

        $response2 = $this->runApp('POST', '/api/user/app/create', ['name' => '']);
        $this->assertEquals(400, $response2->getStatusCode());
    }

    public function testCreate500()
    {
        $this->setupDb([]); // do not add any roles to DB
        $this->loginUser(8);

        $log = new Logger('test');

        $response = $this->runApp('POST', '/api/user/app/create', ['name' => "new\n app"], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ], [
            LoggerInterface::class => $log
        ]);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertSame(
            'AppController->create(): Role "'.Role::APP.'" not found.',
            $log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testCreate201()
    {
        $this->setupDb();
        $this->helper->addRoles([Role::APP]);
        $this->loginUser(8);

        $response = $this->runApp('POST', '/api/user/app/create', ['name' => "new \napp"], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);
        $this->assertEquals(201, $response->getStatusCode());

        $na = $this->appRepo->findOneBy(['name' => 'new app']);
        $this->assertNotNull($na);

        $this->assertSame(
            ['id' => $na->getId(), 'name' => 'new app', 'groups' => [], 'roles' => [Role::APP]],
            $this->parseJsonBody($response)
        );

        $this->assertSame(60, strlen($na->getSecret())); // the hash (blowfish) is 60 chars atm, may change.
        $this->assertSame(1, count($na->getRoles()));
        $this->assertSame(Role::APP, $na->getRoles()[0]->getName());
    }

    public function testRename403()
    {
        $response = $this->runApp('PUT', '/api/user/app/55/rename');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('PUT', '/api/user/app/55/rename');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRename404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/app/'.($this->aid + 1).'/rename', ['name' => "n\n a n"]);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRename400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/rename', ['name' => '']);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testRename200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/rename', ['name' => "n\n a n"], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);
        $response2 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/rename', ['name' => 'new name'], [
            'Content-Type' => 'application/x-www-form-urlencoded'
        ]);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $expectedGroup = ['id' => $this->gid, 'name' => 'group-one',
            'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false];
        $this->assertSame(
            ['id' => $this->aid, 'name' => 'n a n', 'groups' => [$expectedGroup], 'roles' => [Role::APP]],
            $this->parseJsonBody($response1)
        );
        $this->assertSame(
            ['id' => $this->aid, 'name' => 'new name', 'groups' => [$expectedGroup], 'roles' => [Role::APP]],
            $this->parseJsonBody($response2)
        );

        $renamed = $this->appRepo->findOneBy(['name' => 'new name']);
        $this->assertInstanceOf(App::class, $renamed);
    }

    public function testDelete403()
    {
        $response = $this->runApp('DELETE', '/api/user/app/55/delete');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('DELETE', '/api/user/app/55/delete');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDelete404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('DELETE', '/api/user/app/'.($this->aid + 1).'/delete');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDelete204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('DELETE', '/api/user/app/'.$this->aid.'/delete');
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $deleted = $this->appRepo->find($this->aid);
        $this->assertNull($deleted);
    }

    public function testManagers403()
    {
        $response = $this->runApp('GET', '/api/user/app/1/managers');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('GET', '/api/user/app/1/managers');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testManagers404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/app/'.($this->aid + 1).'/managers');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testManagers200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/app/'.$this->aid.'/managers');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->pid3, 'name' => 'Manager', 'roles' => [Role::APP_MANAGER, Role::USER]]],
            $this->parseJsonBody($response)
        );
    }

    public function testAddManager403()
    {
        $response = $this->runApp('PUT', '/api/user/app/59/add-manager/1');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('PUT', '/api/user/app/59/add-manager/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddManager404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/add-manager/'.($this->pid3 + 1));
        $response2 = $this->runApp('PUT', '/api/user/app/'.($this->aid + 1).'/add-manager/'.$this->pid3);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testAddManager204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $player = new Player();
        $player->setName('Manager2');
        $this->em->persist($player);
        $this->em->flush();

        $response1 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/add-manager/'.$this->pid3);
        $response2 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/add-manager/'.$player->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $actual = [];
        $app = $this->appRepo->find($this->aid);
        foreach ($app->getManagers() as $mg) {
            $actual[] = $mg->getId();
        }
        $this->assertSame([$this->pid3, $player->getId()], $actual);
        $this->assertTrue($this->playerRepo->find($player->getId())->hasRole(Role::APP_MANAGER));
    }

    public function testRemoveManager403()
    {
        $response = $this->runApp('PUT', '/api/user/app/59/remove-manager/1');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('PUT', '/api/user/app/59/remove-manager/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveManager404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/app/'.($this->aid + 1).'/remove-manager/'.$this->pid3);
        $response2 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/remove-manager/'.($this->pid3 + 1));
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testRemoveManager204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/remove-manager/'.$this->pid3);
        $this->assertEquals(204, $response->getStatusCode());

        $player = $this->playerRepo->find($this->pid3);
        $actual = [];
        foreach ($player->getManagerGroups() as $mg) {
            $actual[] = $mg->getId();
        }
        $this->assertSame([], $actual);
        $this->assertFalse($player->hasRole(Role::APP_MANAGER));
    }

    public function testShow403()
    {
        $response = $this->runApp('GET', '/api/user/app/1/show');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin and not manager of tested app

        $response = $this->runApp('GET', '/api/user/app/'.($this->aid).'/show');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShow404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/app/'.($this->aid + 1).'/show');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testShow200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/app/'.$this->aid.'/show');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [
                'id' => $this->aid,
                'name' => 'app one',
                'groups' => [['id' => $this->gid, 'name' => 'group-one',
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false]],
                'roles' => [Role::APP]
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testShow200Manager()
    {
        $this->setupDb();
        $this->loginUser(10); // manager of tested group, not an admin

        $response = $this->runApp('GET', '/api/user/app/'.$this->aid.'/show');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [
                'id' => $this->aid,
                'name' => 'app one',
                'groups' => [['id' => $this->gid, 'name' => 'group-one',
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false]],
                'roles' => [Role::APP]
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testAddGroup403()
    {
        $response = $this->runApp('PUT', '/api/user/app/59/add-group/1');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('PUT', '/api/user/app/59/add-group/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddGroup404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/add-group/'.($this->gid + 1));
        $response2 = $this->runApp('PUT', '/api/user/app/'.($this->aid + 1).'/add-group/'.$this->gid);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testAddGroup204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $group = new Group();
        $group->setName('group-two');
        $this->em->persist($group);
        $this->em->flush();

        $response1 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/add-group/'.$this->gid);
        $response2 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/add-group/'.$group->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $actual = [];
        $app = $this->appRepo->find($this->aid);
        foreach ($app->getGroups() as $gp) {
            $actual[] = $gp->getId();
        }
        $this->assertSame([$this->gid, $group->getId()], $actual);
    }

    public function testRemoveGroup403()
    {
        $response = $this->runApp('PUT', '/api/user/app/59/remove-group/1');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('PUT', '/api/user/app/59/remove-group/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveGroup404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/app/'.($this->aid + 1).'/remove-group/'.$this->gid);
        $response2 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/remove-group/'.($this->gid + 1));
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testRemoveGroup500()
    {
        $this->setupDb();
        $this->loginUser(8);

        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $res = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/remove-group/'.$this->gid, null, null, [
            ObjectManager::class => $this->em,
            LoggerInterface::class => $log
        ]);
        $this->assertEquals(500, $res->getStatusCode());
    }

    public function testRemoveGroup204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/remove-group/'.$this->gid);
        $this->assertEquals(204, $response->getStatusCode());

        $group = $this->groupRepo->find($this->gid);
        $actual = [];
        foreach ($group->getApps() as $a) {
            $actual[] = $a->getId();
        }
        $this->assertSame([], $actual);
    }

    public function testAddRole403()
    {
        $response = $this->runApp('PUT', '/api/user/app/101/add-role/r');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('PUT', '/api/user/app/101/add-role/r');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddRole404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/app/101/add-role/r');
        $response2 = $this->runApp('PUT', '/api/user/app/101/add-role/'.Role::APP_TRACKING);
        $response3 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/add-role/role');

        // user is a valid role, but not for apps
        $response4 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/add-role/'.Role::USER);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
    }

    public function testAddRole204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $r1 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/add-role/'.Role::APP_TRACKING);
        $r2 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/add-role/'.Role::APP_TRACKING);
        $this->assertEquals(204, $r1->getStatusCode());
        $this->assertEquals(204, $r2->getStatusCode());

        $this->em->clear();

        $app = $this->appRepo->find($this->aid);
        $this->assertSame(
            [Role::APP, Role::APP_TRACKING],
            $app->getRoleNames()
        );
    }

    public function testRemoveRole403()
    {
        $response = $this->runApp('PUT', '/api/user/app/101/remove-role/r');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(9); // not app-admin

        $response = $this->runApp('PUT', '/api/user/app/101/remove-role/r');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveRole404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/app/101/remove-role/a');
        $response2 = $this->runApp('PUT', '/api/user/app/101/remove-role/'.Role::APP_TRACKING);
        $response3 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/remove-role/a');

        // app is a valid role, but may not be removed
        $response4 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/remove-role/'.Role::APP);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
    }

    public function testRemoveRole204()
    {
        $this->setupDb(['app', 'tracking']); // also add role APP_TRACKING to app
        $this->loginUser(8);

        $r1 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/remove-role/'.Role::APP_TRACKING);
        $r2 = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/remove-role/'.Role::APP_TRACKING);
        $this->assertEquals(204, $r1->getStatusCode());
        $this->assertEquals(204, $r2->getStatusCode());

        $this->em->clear();

        $app = $this->appRepo->find($this->aid);
        $this->assertSame(
            [Role::APP],
            $app->getRoleNames()
        );
    }

    public function testChangeSecret403()
    {
        $response = $this->runApp('PUT', '/api/user/app/59/change-secret');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();

        $this->loginUser(8); // no manager
        $response = $this->runApp('PUT', '/api/user/app/'.($this->aid + 1).'/change-secret');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(9); // manager, but not of this app
        $response = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/change-secret');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testChangeSecret404()
    {
        $this->setupDb();
        $this->loginUser(10);

        $response = $this->runApp('PUT', '/api/user/app/'.($this->aid + 1).'/change-secret');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testChangeSecret200()
    {
        $this->setupDb();
        $this->loginUser(10);

        $response = $this->runApp('PUT', '/api/user/app/'.$this->aid.'/change-secret');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(64, strlen($this->parseJsonBody($response)));
    }

    private function setupDb(array $addRoles = ['app']): void
    {
        $this->helper->emptyDb();

        $roles = [];
        if (count($addRoles) > 0) {
            $roles = $this->helper->addRoles([
                Role::APP,
                Role::APP_TRACKING
            ]);
        }

        $g = $this->helper->addGroups(['group-one']);
        $this->gid = $g[0]->getId();

        $a = new App();
        $a->setName('app one');
        $a->setSecret((string) password_hash('abc123', PASSWORD_BCRYPT));
        if (in_array('app', $addRoles)) {
            $a->addRole($roles[0]); // Role::APP
        }
        if (in_array('tracking', $addRoles)) {
            $a->addRole($roles[1]); // Role::APP_TRACKING
        }
        $this->em->persist($a);

        $char = $this->helper->addCharacterMain('Admin', 8, [Role::USER, Role::APP_ADMIN]);
        $char2 = $this->helper->addCharacterMain('Manager', 9, [Role::USER, Role::APP_MANAGER]);
        $char3 = $this->helper->addCharacterMain('Manager', 10, [Role::USER, Role::APP_MANAGER]);
        $char->getPlayer()->getId();
        $char2->getPlayer()->getId();
        $this->pid3 = $char3->getPlayer()->getId();

        $a->addManager($char3->getPlayer());
        $a->addGroup($g[0]);

        $this->em->flush();
        $this->em->clear();

        $this->aid = $a->getId();
    }
}
