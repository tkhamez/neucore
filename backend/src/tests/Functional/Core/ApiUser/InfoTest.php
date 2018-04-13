<?php
namespace Tests\Functional\Core\ApiUser;

use Brave\Core\Roles;
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
        $groups = $h->addGroups(['group1', 'another-group']);
        $char = $h->addCharacterMain('TUser', 123456, [Roles::USER, Roles::USER_ADMIN], ['group1', 'another-group']);
        $this->loginUser(123456);

        $response = $this->runApp('GET', '/api/user/info');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $char->getPlayer()->getId(),
            'name' => 'TUser',
            'roles' => [Roles::USER, Roles::USER_ADMIN],
            'groups' => [
                ['id' => $groups[1]->getId(), 'name' => 'another-group'],
                ['id' => $groups[0]->getId(), 'name' => 'group1']
            ],
            'characters' => [
                ['id' => 123456, 'name' => 'TUser', 'main' => true],
            ],
            'managerGroups' => [],
            'managerApps' => [],
        ], $this->parseJsonBody($response));
    }
}
