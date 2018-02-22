<?php
namespace Tests\Functional\CoreApiApp;

use Tests\Functional\BaseTestCase;
use Tests\Helper;

class InfoTest extends BaseTestCase
{

    public function testGetInfo401()
    {
        $response = $this->runApp('GET', '/api/app/info');
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetInfo200()
    {
        $h = new Helper();
        $h->emptyDb();
        $aid = $h->addApp('Test App', 'boring-test-secret', ['app']);

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':boring-test-secret')];
        $response = $this->runApp('GET', '/api/app/info', null, $headers);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $aid,
            'name' => 'Test App',
        ], $this->parseJsonBody($response));
    }
}
