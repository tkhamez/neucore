<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api;

use Tests\Functional\WebTestCase;
use Tests\Helper;
use Brave\Core\Roles;

class ApplicationTest extends WebTestCase
{
    public function testShowV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/show');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShowV1200()
    {
        $h = new Helper();
        $h->emptyDb();
        $aid = $h->addApp('Test App', 'boring-test-secret', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':boring-test-secret')];
        $response = $this->runApp('GET', '/api/app/v1/show', null, $headers);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame([
            'id' => $aid,
            'name' => 'Test App',
        ], $this->parseJsonBody($response));
    }

    public function testGroupsV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/groups/123');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupsV1404()
    {
        $h = new Helper();
        $h->emptyDb();
        $aid = $h->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/groups/123', null, $headers);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGroupsV1200()
    {
        $h = new Helper();
        $h->emptyDb();
        $groups = $h->addGroups(['g0', 'g1', 'g2', 'g3']);
        $app = $h->addApp('A1', 's1', ['app']);
        $app->addGroup($groups[0]);
        $app->addGroup($groups[1]);
        $char1 = $h->addCharacterMain('C1', 123, [Roles::USER]);
        $char2 = $h->addCharacterToPlayer('C2', 456, $char1->getPlayer());
        $char1->getPlayer()->addGroup($groups[1]);
        $char2->getPlayer()->addGroup($groups[2]);
        $h->getEm()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($app->getId().':s1')];
        $response1 = $this->runApp('GET', '/api/app/v1/groups/123', null, $headers);
        $response2 = $this->runApp('GET', '/api/app/v1/groups/456', null, $headers);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $body1 = $this->parseJsonBody($response1);
        $body2 = $this->parseJsonBody($response2);

        $this->assertSame($body1, $body2);

        $this->assertSame([
            ['id' => $groups[1]->getId(), 'name' => 'g1', 'public' => false]
        ], $body1);
    }

    public function testMainV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/main/123');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testMainV1404()
    {
        $h = new Helper();
        $h->emptyDb();
        $aid = $h->addApp('A1', 's1', ['app'])->getId();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testMainV1204()
    {
        $h = new Helper();
        $h->emptyDb();
        $aid = $h->addApp('A1', 's1', ['app'])->getId();

        $char = $h->addCharacterMain('C1', 123, [Roles::USER]);
        $char->setMain(false);
        $h->getEm()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testMainV1200()
    {
        $h = new Helper();
        $h->emptyDb();
        $aid = $h->addApp('A1', 's1', ['app'])->getId();
        $char = $h->addCharacterMain('C1', 123, [Roles::USER]);
        $h->addCharacterToPlayer('C2', 456, $char->getPlayer());

        $headers = ['Authorization' => 'Bearer '.base64_encode($aid.':s1')];
        $response1 = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);
        $response2 = $this->runApp('GET', '/api/app/v1/main/456', null, $headers);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $body1 = $this->parseJsonBody($response1);
        $body2 = $this->parseJsonBody($response2);

        $this->assertSame($body1, $body2);
        $this->assertSame(
            [
                'id' => 123,
                'name' => 'C1',
                'main' => true,
                'lastUpdate' => null,
                'validToken' => false,
                'corporation' => null
            ],
            $body1
        );
    }
}
