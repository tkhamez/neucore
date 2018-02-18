<?php
namespace Tests\Functional\CoreApiUser;

use Tests\Functional\BaseTestCase;
use Tests\Helper;

class InfoTest extends BaseTestCase
{

    public function setUp()
    {
        $_SESSION = null;
    }

    public function testGetInfo401()
    {
        $response = $this->runApp('GET', '/api/user/info');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetInfo200()
    {
        $h = new Helper();
        $h->emptyDb();
        $uid = $h->addStandardUser();
        $this->loginUser($uid);

        $response = $this->runApp('GET', '/api/user/info');
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'characterId' => 123456,
            'name' => 'Test User',
            'roles' => ['user'],
            'groups' => []
        ], $this->parseJsonBody($response));
    }
}
