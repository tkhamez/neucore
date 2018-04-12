<?php
namespace Tests\Functional\Core\ApiUser;

use Tests\Functional\WebTestCase;
use Tests\Helper;

class InfoTest extends WebTestCase
{

    public function setUp()
    {
        $_SESSION = null;
    }

    public function testGetInfo403()
    {
        $response = $this->runApp('GET', '/api/user/info');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetInfo200()
    {
        $h = new Helper();
        $h->emptyDb();
        $char = $h->addCharacterMain('Test User', 123456, ['user', 'admin'], ['group1', 'another-group']);
        $this->loginUser(123456);

        $response = $this->runApp('GET', '/api/user/info');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $char->getPlayer()->getId(),
            'name' => 'Test User',
            'roles' => ['admin', 'user'],
            'groups' => ['another-group', 'group1'],
            'characters' => [
                ['id' => 123456, 'name' => 'Test User', 'main' => true],
            ],
            'managerGroups' => [],
            'managerApps' => [],
        ], $this->parseJsonBody($response));
    }
}
