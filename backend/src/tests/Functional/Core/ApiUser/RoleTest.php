<?php
namespace Tests\Functional\Core\ApiUser;

use Tests\Functional\WebTestCase;
use Tests\Helper;
use Brave\Core\Entity\PlayerRepository;
use Brave\Core\Roles;

class RoleTest extends WebTestCase
{
    private $pid;

    private $em;

    public function setUp()
    {
        $_SESSION = null;
    }

    public function testListRoles403()
    {
        $response = $this->runApp('GET', '/api/user/role/list');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListRoles200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/role/list');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [Roles::APP_ADMIN, Roles::APP_MANAGER, Roles::GROUP_ADMIN, Roles::GROUP_MANAGER, Roles::USER_ADMIN],
            $this->parseJsonBody($response)
        );
    }

    public function testListRolesOfPlayer403()
    {
        $response = $this->runApp('GET', '/api/user/role/list-player');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testListRolesOfPlayer400()
    {
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/role/list-player');
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testListRolesOfPlayer404()
    {
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/role/list-player?id=12');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testListRolesOfPlayer200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response = $this->runApp('GET', '/api/user/role/list-player?id='.$this->pid);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(
            [Roles::APP_ADMIN, Roles::USER, Roles::USER_ADMIN],
            $this->parseJsonBody($response)
        );
    }

    public function testAddRoleToPlayer403()
    {
        $response = $this->runApp('PUT', '/api/user/role/add-player');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddRoleToPlayer404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/role/add-player?role=a&player=-1');
        $response2 = $this->runApp('PUT', '/api/user/role/add-player?role='.Roles::APP_MANAGER.'&player=-1');
        $response3 = $this->runApp('PUT', '/api/user/role/add-player?role=a&player='.$this->pid);

        // app is a  valid role, just not for users
        $response4 = $this->runApp('PUT', '/api/user/role/add-player?role=app&player='.$this->pid);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());

    }

    public function testAddRoleToPlayer200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/role/add-player?role='.Roles::APP_MANAGER.'&player='.$this->pid);
        $response2 = $this->runApp('PUT', '/api/user/role/add-player?role='.Roles::APP_MANAGER.'&player='.$this->pid);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $this->em->clear();

        $player = (new PlayerRepository($this->em))->find($this->pid);
        $this->assertSame(
            [Roles::APP_ADMIN, Roles::APP_MANAGER, Roles::USER, Roles::USER_ADMIN],
            $player->getRoleNames()
        );
    }

    public function testRemoveRoleFromPlayer403()
    {
        $response = $this->runApp('PUT', '/api/user/role/remove-player');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveRoleFromPlayer404()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/role/remove-player?role=a&player=-1');
        $response2 = $this->runApp('PUT', '/api/user/role/remove-player?role='.Roles::APP_MANAGER.'&player=-1');
        $response3 = $this->runApp('PUT', '/api/user/role/remove-player?role=a&player='.$this->pid);

        // user is a valid role, but may not be removed
        $response4 = $this->runApp('PUT', '/api/user/role/remove-player?role=user&player='.$this->pid);

        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
        $this->assertEquals(404, $response4->getStatusCode());
    }

    public function testRemoveRoleFromPlayer200()
    {
        $this->setupDb();
        $this->loginUser(12);

        $response1 = $this->runApp('PUT', '/api/user/role/remove-player?role='.Roles::APP_ADMIN.'&player='.$this->pid);
        $response2 = $this->runApp('PUT', '/api/user/role/remove-player?role='.Roles::APP_ADMIN.'&player='.$this->pid);
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

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
