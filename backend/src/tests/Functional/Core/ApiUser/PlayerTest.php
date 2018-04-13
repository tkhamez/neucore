<?php
namespace Tests\Functional\Core\ApiUser;

use Brave\Core\Roles;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class PlayerTest extends WebTestCase
{

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

    private function setupDb()
    {
        $h = new Helper();
        $h->emptyDb();
        $char = $h->addCharacterMain('Admin', 12, [Roles::USER, Roles::APP_ADMIN, Roles::USER_ADMIN]);
        $this->pid = $char->getPlayer()->getId();
    }
}
