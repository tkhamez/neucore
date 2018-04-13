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
}
