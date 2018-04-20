<?php
namespace Tests\Functional\Core\Api;

use Tests\Functional\WebTestCase;
use Tests\Helper;

class ApplicationTest extends WebTestCase
{
    public function testGetInfoV1403()
    {
        $response = $this->runApp('GET', '/api/app/info/v1');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGetInfoV1200()
    {
        $h = new Helper();
        $h->emptyDb();
        $aid = $h->addApp('Test App', 'boring-test-secret', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':boring-test-secret')];
        $response = $this->runApp('GET', '/api/app/info/v1', null, $headers);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $aid,
            'name' => 'Test App',
        ], $this->parseJsonBody($response));
    }
}
