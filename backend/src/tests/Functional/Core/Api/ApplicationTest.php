<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api;

use Brave\Core\Roles;
use Brave\Core\Entity\Group;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Alliance;

class ApplicationTest extends WebTestCase
{
    private $appId;

    private $group0Id;

    private $group1Id;

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
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response1 = $this->runApp('GET', '/api/app/v1/groups/123', null, $headers);
        $response2 = $this->runApp('GET', '/api/app/v1/groups/456', null, $headers);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $body1 = $this->parseJsonBody($response1);
        $body2 = $this->parseJsonBody($response2);

        $this->assertSame($body1, $body2);

        $this->assertSame([
            ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE]
        ], $body1);
    }

    public function testGroupsBulkV1403()
    {
        $response = $this->runApp('POST', '/api/app/v1/groups');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testGroupsBulkV1400()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('POST', '/api/app/v1/groups', new \stdClass(), $headers);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGroupsBulkV1200()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('POST', '/api/app/v1/groups', [123, 789, 789, 12], $headers);

        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->parseJsonBody($response);

        $expected = [[
            'character' => ['id' => 123, 'name' => 'C1', 'corporation' => null],
            'groups' => [
                ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE]
            ],
        ], [
            'character' => ['id' => 789, 'name' => 'C3', 'corporation' => [
                'id' => 500, 'name' => 'five', 'ticker' => '-5-', 'alliance' => [
                    'id' => 100, 'name' => 'one', 'ticker' => '-1-'
                ]
            ]],
            'groups' => [
                ['id' => $this->group0Id, 'name' => 'g0', 'visibility' => Group::VISIBILITY_PRIVATE],
                ['id' => $this->group1Id, 'name' => 'g1', 'visibility' => Group::VISIBILITY_PRIVATE],
            ],
        ]];
        $this->assertSame($expected, $body);
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

    private function setUpDb()
    {
        $h = new Helper();
        $h->emptyDb();

        $groups = $h->addGroups(['g0', 'g1', 'g2', 'g3']);
        $this->group0Id = $groups[0]->getId();
        $this->group1Id = $groups[1]->getId();

        $app = $h->addApp('A1', 's1', ['app']);
        $app->addGroup($groups[0]);
        $app->addGroup($groups[1]);
        $this->appId = $app->getId();

        $char1 = $h->addCharacterMain('C1', 123, [Roles::USER]);
        $char2 = $h->addCharacterToPlayer('C2', 456, $char1->getPlayer());

        $char1->getPlayer()->addGroup($groups[1]);
        $char2->getPlayer()->addGroup($groups[2]);

        $alli = (new Alliance())->setId(100)->setName('one')->setTicker('-1-');
        $corp = (new Corporation())->setId(500)->setName('five')->setTicker('-5-');
        $corp->setAlliance($alli);
        $h->getEm()->persist($alli);
        $h->getEm()->persist($corp);

        $char3 = $h->addCharacterMain('C3', 789); // no roles
        $char3->setCorporation($corp);
        $char3->getPlayer()->addGroup($groups[0]);
        $char3->getPlayer()->addGroup($groups[1]);

        $h->getEm()->flush();
    }
}
