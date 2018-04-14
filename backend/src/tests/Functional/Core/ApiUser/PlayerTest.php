<?php
namespace Tests\Functional\Core\ApiUser;

use Brave\Core\Roles;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Brave\Core\Entity\PlayerRepository;

class PlayerTest extends WebTestCase
{

    private $em;

    private $pid;

    public function setUp()
    {
        $_SESSION = null;
    }

    public function testList403()
    {
        $response = $this->runApp('GET', '/api/user/player/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testList200()
    {
        $h = new Helper();
        $h->emptyDb();
        $admin = $h->addCharacterMain('Admin', 12, [Roles::USER_ADMIN]);
        $user = $h->addCharacterMain('User', 45, [Roles::USER]);
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            ['id' => $admin->getPlayer()->getId(), 'name' => 'Admin'],
            ['id' => $user->getPlayer()->getId(), 'name' => 'User'],
        ], $this->parseJsonBody($response));
    }

    public function testListGroupManager403()
    {
        $response = $this->runApp('GET', '/api/user/player/list-group-manager');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListGroupManager200()
    {
        $h = new Helper();
        $h->emptyDb();
        $h->addCharacterMain('Admin', 12, [Roles::GROUP_ADMIN]);
        $manager = $h->addCharacterMain('Manager', 45, [Roles::GROUP_MANAGER]);
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/list-group-manager');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [['id' => $manager->getPlayer()->getId(), 'name' => 'Manager']],
            $this->parseJsonBody($response)
        );
    }

    public function testListRoles403()
    {
        $response = $this->runApp('GET', '/api/user/player/1/roles');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListRoles404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/'.($this->pid + 1).'/roles');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testListRoles200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/player/'.$this->pid.'/roles');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [Roles::APP_ADMIN, Roles::USER, Roles::USER_ADMIN],
            $this->parseJsonBody($response)
        );
    }

    public function testAddRole403()
    {
        $response = $this->runApp('PUT', '/api/user/player/101/add-role');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddRole404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/player/101/add-role');
        $response2 = $this->runApp('PUT', '/api/user/player/101/add-role?role='.Roles::APP_MANAGER);
        $response3 = $this->runApp('PUT', '/api/user/player/'.$this->pid.'/add-role?role=role');

        // app is a valid role, just not for users
        $response4 = $this->runApp('PUT', '/api/user/player/'.$this->pid.'/add-role?role='.Roles::APP);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
    }

    public function testAddRole204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/player/'.($this->pid).'/add-role?role='.Roles::APP_MANAGER);
        $response2 = $this->runApp('PUT', '/api/user/player/'.($this->pid).'/add-role?role='.Roles::APP_MANAGER);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $player = (new PlayerRepository($this->em))->find($this->pid);
        $this->assertSame(
            [Roles::APP_ADMIN, Roles::APP_MANAGER, Roles::USER, Roles::USER_ADMIN],
            $player->getRoleNames()
        );
    }

    public function testRemoveRole403()
    {
        $response = $this->runApp('PUT', '/api/user/player/101/remove-role');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveRole404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/player/101/remove-role?role=a');
        $response2 = $this->runApp('PUT', '/api/user/player/101/remove-role?role='.Roles::APP_MANAGER);
        $response3 = $this->runApp('PUT', '/api/user/player/'.$this->pid.'/remove-role?role=a');

        // user is a valid role, but may not be removed
        $response4 = $this->runApp('PUT', '/api/user/player/'.$this->pid.'/remove-role?role='.Roles::USER);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
    }

    public function testRemoveRole204()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/player/'.$this->pid.'/remove-role?role='.Roles::APP_ADMIN);
        $response2 = $this->runApp('PUT', '/api/user/player/'.$this->pid.'/remove-role?role='.Roles::APP_ADMIN);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());

        $this->em->clear();

        $player = (new PlayerRepository($this->em))->find($this->pid);
        $this->assertSame(
            [Roles::USER, Roles::USER_ADMIN],
            $player->getRoleNames()
            );
    }

    private function setupDb()
    {
        $h = new Helper();
        $h->emptyDb();
        $this->em = $h->getEm();
        $h->addRoles([
            Roles::USER,
            Roles::APP,
            Roles::APP_ADMIN,
            Roles::APP_MANAGER,
            Roles::GROUP_ADMIN,
            Roles::GROUP_MANAGER,
            Roles::USER_ADMIN
        ]);
        $char = $h->addCharacterMain('Admin', 12, [Roles::USER, Roles::APP_ADMIN, Roles::USER_ADMIN]);
        $this->pid = $char->getPlayer()->getId();
    }
}
