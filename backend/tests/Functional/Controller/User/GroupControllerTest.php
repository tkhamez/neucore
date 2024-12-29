<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Role;
use Neucore\Repository\GroupApplicationRepository;
use Neucore\Repository\GroupRepository;
use Neucore\Entity\Player;
use Neucore\Repository\PlayerRepository;
use Neucore\Factory\RepositoryFactory;
use Doctrine\ORM\Events;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Psr\Log\LoggerInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\WriteErrorListener;

class GroupControllerTest extends WebTestCase
{
    private static WriteErrorListener $writeErrorListener;

    private Helper $helper;

    private EntityManagerInterface $em;

    private GroupRepository $groupRepo;

    private PlayerRepository $playerRepo;

    private GroupApplicationRepository $groupAppRepo;

    private int $gid;

    private int $gid2;

    private int $gidReq;

    private int $gid4;

    private int $gidForbidden;

    private int $pid;

    private int $pid2;

    private ?int $groupApp1Id = null;

    private ?int $groupApp2Id = null;

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
        $this->groupRepo = $repositoryFactory->getGroupRepository();
        $this->playerRepo = $repositoryFactory->getPlayerRepository();
        $this->groupAppRepo = $repositoryFactory->getGroupApplicationRepository();
    }

    public function tearDown(): void
    {
        $this->em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testAll403()
    {
        $response = $this->runApp('GET', '/api/user/group/all');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not a group-admin

        $response = $this->runApp('GET', '/api/user/group/all');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAll200()
    {
        $this->setupDb();
        $this->loginUser(8); # GROUP_ADMIN

        $response1 = $this->runApp('GET', '/api/user/group/all');
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertSame(
            [
                ['id' => $this->gidForbidden, 'name' => 'forbidden-group', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false],
                ['id' => $this->gid, 'name' => 'group-one', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false],
                ['id' => $this->gid2, 'name' => 'group-public', 'description' => null,
                    'visibility' => Group::VISIBILITY_PUBLIC, 'autoAccept' => false, 'isDefault' => false],
                ['id' => $this->gid4, 'name' => 'group-public2', 'description' => null,
                    'visibility' => Group::VISIBILITY_PUBLIC, 'autoAccept' => false, 'isDefault' => false],
                ['id' => $this->gidReq, 'name' => 'required-group', 'description' => null,
                    'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false],
            ],
            $this->parseJsonBody($response1),
        );
    }

    public function testPublic403()
    {
        $response = $this->runApp('GET', '/api/user/group/public');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testPublic200()
    {
        $this->setupDb();
        $this->loginUser(6); # only USER

        $response1 = $this->runApp('GET', '/api/user/group/public');
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertSame(
            [['id' => $this->gid2, 'name' => 'group-public', 'description' => null,
                'visibility' => Group::VISIBILITY_PUBLIC, 'autoAccept' => false, 'isDefault' => false]],
            $this->parseJsonBody($response1),
        );
    }

    public function testCreate403()
    {
        $response = $this->runApp('POST', '/api/user/group/create');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); // not group-admin

        $response = $this->runApp('POST', '/api/user/group/create');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCreate400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('POST', '/api/user/group/create');
        $this->assertEquals(400, $response1->getStatusCode());

        $response2 = $this->runApp('POST', '/api/user/group/create', ['name' => 'in va lid']);
        $this->assertEquals(400, $response2->getStatusCode());
    }

    public function testCreate409()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('POST', '/api/user/group/create', ['name' => 'group-one'], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testCreate201()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('POST', '/api/user/group/create', ['name' => 'new-g'], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $this->assertEquals(201, $response->getStatusCode());

        $ng = $this->groupRepo->findOneBy(['name' => 'new-g']);
        $this->assertSame(
            ['id' => $ng->getId(), 'name' => 'new-g', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false],
            $this->parseJsonBody($response),
        );
    }

    public function testRename403()
    {
        $response = $this->runApp('PUT', '/api/user/group/66/rename');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); // not group-admin

        $response = $this->runApp('PUT', '/api/user/group/66/rename');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRename404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 5) . '/rename', ['name' => 'new-g']);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRename400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/rename', ['name' => '']);
        $this->assertEquals(400, $response1->getStatusCode());

        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/rename', ['name' => 'in va lid']);
        $this->assertEquals(400, $response2->getStatusCode());
    }

    public function testRename409()
    {
        $this->setupDb();
        $this->loginUser(8);

        $this->helper->addGroups(['group-two']);

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/rename', ['name' => 'group-two'], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testRename200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/rename', ['name' => 'group-one'], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/rename', ['name' => 'new-name'], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $this->assertSame(
            ['id' => $this->gid, 'name' => 'group-one', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false],
            $this->parseJsonBody($response1),
        );

        $this->assertSame(
            ['id' => $this->gid, 'name' => 'new-name', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false],
            $this->parseJsonBody($response2),
        );

        $renamed = $this->groupRepo->findOneBy(['name' => 'new-name']);
        $this->assertInstanceOf(Group::class, $renamed);
    }

    public function testUpdateDescription403()
    {
        $response = $this->runApp('PUT', '/api/user/group/66/update-description');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); // not group-admin

        $response = $this->runApp('PUT', '/api/user/group/66/update-description');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testUpdateDescription404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp(
            'PUT',
            '/api/user/group/' . ($this->gid + 5) . '/update-description',
            ['description' => ''],
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateDescription200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp(
            'PUT',
            '/api/user/group/' . $this->gid . '/update-description',
            ['description' => "A\ndescription"],
            ['Content-Type' => 'application/x-www-form-urlencoded'],
        );
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            ['id' => $this->gid, 'name' => 'group-one', 'description' => "A\ndescription",
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false],
            $this->parseJsonBody($response),
        );

        $group = $this->groupRepo->find($this->gid);
        $this->assertSame("A\ndescription", $group->getDescription());
    }

    public function testSetVisibility403()
    {
        $response = $this->runApp('PUT', '/api/user/group/66/set-visibility/public');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); // not a group-admin

        $response = $this->runApp('PUT', '/api/user/group/66/set-visibility/public');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSetVisibility404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 5) . '/set-visibility/public');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSetVisibility400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/set-visibility/invalid');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testSetVisibility200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/set-visibility/public');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            ['id' => $this->gid, 'name' => 'group-one', 'description' => null,
                'visibility' => Group::VISIBILITY_PUBLIC, 'autoAccept' => false, 'isDefault' => false],
            $this->parseJsonBody($response),
        );

        $this->em->clear();

        $changed = $this->groupRepo->find($this->gid);
        $this->assertSame(Group::VISIBILITY_PUBLIC, $changed->getVisibility());
    }

    public function testSetAutoAccept403()
    {
        $response = $this->runApp('PUT', '/api/user/group/66/set-auto-accept/on');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); // not a group-admin

        $response = $this->runApp('PUT', '/api/user/group/66/set-auto-accept/on');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSetAutoAccept404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 5) . '/set-auto-accept/on');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSetAutoAccept400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/set-auto-accept/invalid');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testSetAutoAccept200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/set-auto-accept/off');
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/set-auto-accept/on');
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $this->assertSame(
            ['id' => $this->gid, 'name' => 'group-one', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => true, 'isDefault' => false],
            $this->parseJsonBody($response2),
        );

        $this->em->clear();

        $changed = $this->groupRepo->find($this->gid);
        $this->assertTrue($changed->getAutoAccept());
    }

    public function testSetIsDefault403()
    {
        $response = $this->runApp('PUT', '/api/user/group/66/set-is-default/on');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); // not a group-admin

        $response = $this->runApp('PUT', '/api/user/group/66/set-is-default/on');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testSetIsDefault404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 5) . '/set-is-default/on');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testSetIsDefault400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/set-is-default/invalid');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testSetIsDefault200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/set-is-default/off');
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/set-is-default/on');
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $this->assertSame(
            ['id' => $this->gid, 'name' => 'group-one', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => true],
            $this->parseJsonBody($response2),
        );

        $this->em->clear();

        $changed = $this->groupRepo->find($this->gid);
        $this->assertTrue($changed->getIsDefault());
    }

    public function testDelete403()
    {
        $response = $this->runApp('DELETE', '/api/user/group/66/delete');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); // not group-admin

        $response = $this->runApp('DELETE', '/api/user/group/66/delete');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDelete404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('DELETE', '/api/user/group/' . ($this->gid + 5) . '/delete');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDelete204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('DELETE', '/api/user/group/' . $this->gid . '/delete');
        $this->assertEquals(204, $response->getStatusCode());

        $this->em->clear();

        $deleted = $this->groupRepo->find($this->gid);
        $this->assertNull($deleted);
    }

    public function testManagers403()
    {
        $response = $this->runApp('GET', '/api/user/group/1/managers');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not an admin

        $response = $this->runApp('GET', '/api/user/group/1/managers');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testManagers404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/' . ($this->gid + 5) . '/managers');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testManagers200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/' . $this->gid . '/managers');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->pid, 'name' => 'Admin', 'roles' => [Role::GROUP_ADMIN, Role::GROUP_MANAGER, Role::USER]]],
            $this->parseJsonBody($response),
        );
    }

    public function testManagers200_RoleManager()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('GET', '/api/user/group/' . $this->gid . '/managers');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->pid, 'name' => 'Admin']],
            $this->parseJsonBody($response),
        );
    }

    public function testCorporations403()
    {
        $response = $this->runApp('GET', '/api/user/group/1/corporations');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not an admin

        $response = $this->runApp('GET', '/api/user/group/1/corporations');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCorporations404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/' . ($this->gid + 5) . '/corporations');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCorporations200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/' . $this->gid . '/corporations');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => 200, 'name' => 'corp 2', 'ticker' => 'c2', 'alliance' => [
                'id' => 10, 'name' => 'alli 1', 'ticker' => 'a1',
            ]]],
            $this->parseJsonBody($response),
        );
    }

    public function testAlliances403()
    {
        $response = $this->runApp('GET', '/api/user/group/1/alliances');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); # not an admin

        $response = $this->runApp('GET', '/api/user/group/1/alliances');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAlliances404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/' . ($this->gid + 5) . '/alliances');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAlliances200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/' . $this->gid . '/alliances');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => 10, 'name' => 'alli 1', 'ticker' => 'a1']],
            $this->parseJsonBody($response),
        );
    }

    public function testRequiredGroup403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/group/' . $this->gid . '/required-groups');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6); // not a group admin or manager
        $response2 = $this->runApp('GET', '/api/user/group/' . $this->gid . '/required-groups');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testRequiredGroup404()
    {
        $this->setupDb();

        $this->loginUser(8);
        $response = $this->runApp('GET', '/api/user/group/' . ($this->gid + 9) . '/required-groups');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testRequiredGroup200()
    {
        $this->setupDb();

        $this->loginUser(8);
        $response = $this->runApp('GET', '/api/user/group/' . $this->gid . '/required-groups');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->gidReq, 'name' => 'required-group', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false]],
            $this->parseJsonBody($response),
        );
    }

    public function testAddRequiredGroup403()
    {
        $this->setupDb();

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-required/' . $this->gid2);
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6); // not a group admin
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-required/' . $this->gid2);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testAddRequiredGroup404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 9) . '/add-required/' . $this->gid2);
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-required/' . ($this->gid2 + 9));
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testAddRequiredGroup204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-required/' . $this->gid2);
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-required/' . $this->gid2);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $group = $this->groupRepo->find($this->gid);
        $actual = $group->getRequiredGroups();
        $this->assertSame(2, count($actual));
        $this->assertSame($this->gid2, $actual[0]->getId());
        $this->assertSame($this->gidReq, $actual[1]->getId());
    }

    public function testRemoveRequiredGroup403()
    {
        $this->setupDb();

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-required/' . $this->gidReq);
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6); // not a group admin

        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-required/' . $this->gidReq);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testRemoveRequiredGroup404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 9) . '/remove-required/' . $this->gidReq);
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-required/' . ($this->gidReq + 9));
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testRemoveRequiredGroup204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-required/' . $this->gidReq);
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-required/' . $this->gidReq);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $group = $this->groupRepo->find($this->gid);
        $this->assertSame(0, count($group->getRequiredGroups()));
    }

    public function testForbiddenGroup403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/group/' . $this->gid . '/forbidden-groups');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6); // not a group admin or manager
        $response2 = $this->runApp('GET', '/api/user/group/' . $this->gid . '/forbidden-groups');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testForbiddenGroup404()
    {
        $this->setupDb();

        $this->loginUser(8);
        $response = $this->runApp('GET', '/api/user/group/' . ($this->gid + 9) . '/forbidden-groups');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testForbiddenGroup200()
    {
        $this->setupDb();

        $this->loginUser(8);
        $response = $this->runApp('GET', '/api/user/group/' . $this->gid . '/forbidden-groups');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->gidForbidden, 'name' => 'forbidden-group', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false, 'isDefault' => false]],
            $this->parseJsonBody($response),
        );
    }

    public function testAddForbiddenGroup403()
    {
        $this->setupDb();

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-forbidden/' . $this->gid2);
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6); // not a group admin
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-forbidden/' . $this->gid2);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testAddForbiddenGroup404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 9) . '/add-forbidden/' . $this->gid2);
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-forbidden/' . ($this->gid2 + 9));
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testAddForbiddenGroup204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-forbidden/' . $this->gid2);
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-forbidden/' . $this->gid2);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $group = $this->groupRepo->find($this->gid);
        $actual = $group->getForbiddenGroups();
        $this->assertSame(2, count($actual));
        $this->assertSame($this->gidForbidden, $actual[0]->getId());
        $this->assertSame($this->gid2, $actual[1]->getId());
    }

    public function testRemoveForbiddenGroup403()
    {
        $this->setupDb();

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-forbidden/' . $this->gidReq);
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6); // not a group admin

        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-forbidden/' . $this->gidReq);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testRemoveForbiddenGroup404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp(
            'PUT',
            '/api/user/group/' . ($this->gid + 9) . '/remove-forbidden/' . $this->gidForbidden,
        );
        $response2 = $this->runApp(
            'PUT',
            '/api/user/group/' . $this->gid . '/remove-forbidden/' . ($this->gidForbidden + 9),
        );
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testRemoveForbiddenGroup204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-forbidden/' . $this->gidForbidden);
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-forbidden/' . $this->gidForbidden);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $group = $this->groupRepo->find($this->gid);
        $this->assertSame(0, count($group->getForbiddenGroups()));
    }

    public function testAddManager403()
    {
        $response = $this->runApp('PUT', '/api/user/group/69/add-manager/1');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); // not group-admin

        $response = $this->runApp('PUT', '/api/user/group/69/add-manager/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddManager404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-manager/' . ($this->pid + 5));
        $response2 = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 5) . '/add-manager/' . $this->pid);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testAddManager204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $player = new Player();
        $player->setName('Manager');
        $this->em->persist($player);
        $this->em->flush();

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-manager/' . $this->pid);
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-manager/' . $player->getId());
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $actual = [];
        $group = $this->groupRepo->find($this->gid);
        foreach ($group->getManagers() as $mg) {
            $actual[] = $mg->getId();
        }
        $this->assertSame([$this->pid, $player->getId()], $actual);
        $this->assertTrue($this->playerRepo->find($player->getId())->hasRole(Role::GROUP_MANAGER));
    }

    public function testRemoveManager403()
    {
        $response = $this->runApp('PUT', '/api/user/group/69/remove-manager/1');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setupDb();
        $this->loginUser(6); // not group-admin

        $response = $this->runApp('PUT', '/api/user/group/69/remove-manager/1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveManager404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 5) . '/remove-manager/' . $this->pid);
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-manager/' . ($this->pid + 5));
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
    }

    public function testRemoveManager204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-manager/' . $this->pid);
        $this->assertEquals(204, $response->getStatusCode());
        $this->em->clear();

        $player = $this->playerRepo->find($this->pid);
        $actual = [];
        foreach ($player->getManagerGroups() as $mg) {
            $actual[] = $mg->getId();
        }
        $this->assertSame([$this->gid2], $actual);
        $this->assertTrue($player->hasRole(Role::GROUP_MANAGER));

        $this->runApp('PUT', '/api/user/group/' . $this->gid2 . '/remove-manager/' . $this->pid);
        $this->em->clear();
        $this->assertFalse($this->playerRepo->find($this->pid)->hasRole(Role::GROUP_MANAGER));
    }

    public function testApplications403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/group/' . $this->gid2 . '/applications');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(7); // not manager of that group

        $response2 = $this->runApp('GET', '/api/user/group/' . $this->gid2 . '/applications');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testApplications404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/' . ($this->gid2 + 5) . '/applications');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testApplications200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/' . $this->gid2 . '/applications');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [[
                'id' => $this->groupApp1Id,
                'player' => ['id' => $this->pid2, 'name' => 'Group'],
                'group' => [
                    'id' => $this->gid2,
                    'name' => 'group-public',
                    'description' => null,
                    'visibility' => Group::VISIBILITY_PUBLIC,
                    'autoAccept' => false,
                    'isDefault' => false,
                ],
                'status' => GroupApplication::STATUS_PENDING,
                'created' => null,
            ]],
            $this->parseJsonBody($response),
        );
    }

    public function testDenyApplication403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/group/deny-application/' . $this->groupApp1Id);
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); // manager, but not of this group
        $response = $this->runApp('PUT', '/api/user/group/deny-application/' . $this->groupApp1Id);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testDenyApplication404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/deny-application/' . ($this->groupApp1Id + 5));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDenyApplication204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $appsBefore = $this->groupAppRepo->findBy(['player' => $this->pid2, 'group' => $this->gid2]);
        $this->assertSame(1, count($appsBefore));
        $this->assertSame(GroupApplication::STATUS_PENDING, $appsBefore[0]->getStatus());
        $this->assertFalse($this->playerRepo->find($this->pid2)->hasGroup($this->gid2));

        $response1 = $this->runApp('PUT', '/api/user/group/deny-application/' . $this->groupApp1Id);
        $response2 = $this->runApp('PUT', '/api/user/group/deny-application/' . $this->groupApp1Id);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $appsAfter = $this->groupAppRepo->findBy(['player' => $this->pid2, 'group' => $this->gid2]);
        $this->assertSame(1, count($appsAfter));
        $this->assertSame(GroupApplication::STATUS_DENIED, $appsAfter[0]->getStatus());
        $this->assertFalse($this->playerRepo->find($this->pid2)->hasGroup($this->gid2));
    }

    public function testAcceptApplication403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/group/accept-application/' . $this->groupApp1Id);
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); // manager, but not of this group
        $response = $this->runApp('PUT', '/api/user/group/accept-application/' . $this->groupApp1Id);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAcceptApplication404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/accept-application/' . ($this->groupApp1Id + 5));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAcceptApplication400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/accept-application/' . $this->groupApp2Id);
        $this->assertEquals(400, $response->getStatusCode());

        $this->em->clear();

        $appsAfter = $this->groupAppRepo->findBy(['player' => $this->pid2, 'group' => $this->gid2]);
        $this->assertSame(1, count($appsAfter));
        $this->assertSame(GroupApplication::STATUS_PENDING, $appsAfter[0]->getStatus());
        $this->assertFalse($this->playerRepo->find($this->pid2)->hasGroup($this->gid2));
    }

    public function testAcceptApplication204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $appsBefore = $this->groupAppRepo->findBy(['player' => $this->pid2, 'group' => $this->gid2]);
        $this->assertSame(1, count($appsBefore));
        $this->assertSame(GroupApplication::STATUS_PENDING, $appsBefore[0]->getStatus());
        $this->assertFalse($this->playerRepo->find($this->pid2)->hasGroup($this->gid2));

        $response1 = $this->runApp('PUT', '/api/user/group/accept-application/' . $this->groupApp1Id);
        $response2 = $this->runApp('PUT', '/api/user/group/accept-application/' . $this->groupApp1Id);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $appsAfter = $this->groupAppRepo->findBy(['player' => $this->pid2, 'group' => $this->gid2]);
        $this->assertSame(1, count($appsAfter));
        $this->assertSame(GroupApplication::STATUS_ACCEPTED, $appsAfter[0]->getStatus());
        $this->assertTrue($this->playerRepo->find($this->pid2)->hasGroup($this->gid2));
    }

    public function testAddMember403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-member/' . $this->pid);
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); // manager, but not of this group
        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-member/' . $this->pid);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddMember404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 5) . '/add-member/' . $this->pid);
        $this->assertEquals(404, $response1->getStatusCode());

        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-member/' . ($this->pid + 5));
        $this->assertEquals(404, $response2->getStatusCode());

        $response3 = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 5) . '/add-member/' . ($this->pid + 5));
        $this->assertEquals(404, $response3->getStatusCode());
    }

    public function testAddMember400()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-member/' . $this->pid);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAddMember204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/add-member/' . $this->pid2); // already member
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid2 . '/add-member/' . $this->pid);
        $response3 = $this->runApp('PUT', '/api/user/group/' . $this->gid2 . '/add-member/' . $this->pid);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());
        $this->assertEquals(204, $response3->getStatusCode());

        $this->em->clear();

        $this->assertSame(1, count($this->groupRepo->find($this->gid2)->getPlayers()));
    }

    public function testAddMember204AsManager()
    {
        $this->setupDb();
        $this->loginUser(9);  // only user-manager, not an admin

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid2 . '/add-member/' . $this->pid);
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testRemoveMember403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-member/' . $this->pid);
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(7); // manager, but not of this group
        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-member/' . $this->pid);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveMember404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 5) . '/remove-member/' . $this->pid);
        $this->assertEquals(404, $response1->getStatusCode());

        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-member/' . ($this->pid + 5));
        $this->assertEquals(404, $response2->getStatusCode());

        $response3 = $this->runApp('PUT', '/api/user/group/' . ($this->gid + 5) . '/remove-member/' . ($this->pid + 5));
        $this->assertEquals(404, $response3->getStatusCode());
    }

    public function testRemoveMember500()
    {
        $this->setupDb();
        $this->loginUser(8);

        $this->em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $res = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-member/' . $this->pid2, null, null, [
            ObjectManager::class => $this->em,
            LoggerInterface::class => $log,
        ]);
        $this->assertEquals(500, $res->getStatusCode());
    }

    public function testRemoveMember204()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response1 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-member/' . $this->pid); // not member
        $response2 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-member/' . $this->pid2);
        $response3 = $this->runApp('PUT', '/api/user/group/' . $this->gid . '/remove-member/' . $this->pid2);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());
        $this->assertEquals(204, $response3->getStatusCode());

        $this->em->clear();

        $group = $this->groupRepo->find($this->gid);
        $this->assertSame(0, count($group->getPlayers()));
        $this->assertSame(1, count($this->groupAppRepo->findBy(['player' => $this->pid2])));
    }

    public function testRemoveMember204AsManager()
    {
        $this->setupDb();
        $this->loginUser(9); // only user-manager, not an admin

        $response = $this->runApp('PUT', '/api/user/group/' . $this->gid2 . '/remove-member/' . $this->pid);
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testMembers403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/group/1/members');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6); # not a manager

        $response2 = $this->runApp('GET', '/api/user/group/1/members');
        $this->assertEquals(403, $response2->getStatusCode());

        $this->loginUser(7); # manager, but not of this group

        $response3 = $this->runApp('GET', '/api/user/group/' . $this->gid . '/members');
        $this->assertEquals(403, $response3->getStatusCode());
    }

    public function testMembers404()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/' . ($this->gid + 5) . '/members');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testMembers200()
    {
        $this->setupDb();
        $this->loginUser(8);

        $response = $this->runApp('GET', '/api/user/group/' . $this->gid . '/members');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $this->pid2, 'name' => 'Group', 'characterId' => 7,
                'corporationName' => 'corp 2', 'allianceName' => 'alli 1']],
            $this->parseJsonBody($response),
        );
    }

    private function setupDb(): void
    {
        $this->helper->emptyDb();

        $g = $this->helper->addGroups([
            'group-one', 'group-public', 'required-group', 'group-public2', 'forbidden-group',
        ]);
        $this->gid = $g[0]->getId();
        $this->gid2 = $g[1]->getId();
        $this->gidReq = $g[2]->getId();
        $this->gid4 = $g[3]->getId();
        $this->gidForbidden = $g[4]->getId();
        $g[0]->addRequiredGroup($g[2]);
        $g[3]->addRequiredGroup($g[2]);
        $g[0]->addForbiddenGroup($g[4]);
        $g[1]->setVisibility(Group::VISIBILITY_PUBLIC);
        $g[3]->setVisibility(Group::VISIBILITY_PUBLIC);

        $this->helper->addCharacterMain('User', 6, [Role::USER]);

        // group manager, but not of any group
        $user = $this->helper->addCharacterMain('Group', 7, [Role::USER, Role::GROUP_MANAGER], ['group-one']);
        $this->pid2 = $user->getPlayer()->getId();

        $admin = $this->helper->addCharacterMain('Admin', 8, [Role::USER, Role::GROUP_MANAGER, Role::GROUP_ADMIN]);
        $this->pid = $admin->getPlayer()->getId();

        $this->helper->addCharacterMain('UA', 9, [Role::USER_MANAGER]);

        $g[0]->addManager($admin->getPlayer());
        $g[1]->addManager($admin->getPlayer());

        $groupApp1 = (new GroupApplication())->setPlayer($user->getPlayer())->setGroup($g[1]);
        $groupApp2 = (new GroupApplication())->setPlayer($user->getPlayer())->setGroup($g[0]);
        $this->em->persist($groupApp1);
        $this->em->persist($groupApp2);

        // corps and alliances
        $alli = (new Alliance())->setId(10)->setTicker('a1')->setName('alli 1');
        $corp = (new Corporation())->setId(200)->setTicker('c2')->setName('corp 2')->setAlliance($alli);
        $alli->addGroup($g[0]);
        $corp->addGroup($g[0]);
        $user->setCorporation($corp);
        $this->em->persist($alli);
        $this->em->persist($corp);

        $this->helper->addRoles([Role::TRACKING, Role::WATCHLIST, Role::WATCHLIST_MANAGER]);

        $this->em->flush();
        $this->em->clear();

        $this->groupApp1Id = $groupApp1->getId();
        $this->groupApp2Id = $groupApp2->getId();
    }
}
