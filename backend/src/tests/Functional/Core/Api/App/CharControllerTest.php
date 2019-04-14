<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\App;

use Brave\Core\Entity\Role;
use Brave\Core\Factory\RepositoryFactory;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class CharControllerTest extends WebTestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

    private $app0Id;

    private $appId;

    public function setUp()
    {
        $this->helper = new Helper();
        $this->repoFactory = new RepositoryFactory($this->helper->getEm());
    }

    public function testMainV1403()
    {
        $response1 = $this->runApp('GET', '/api/app/v1/main/123');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s1')];
        $response2 = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testMainV1404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Not Found', $response->getReasonPhrase());
    }

    public function testMainV2403()
    {
        $response1 = $this->runApp('GET', '/api/app/v2/main/123');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s1')];
        $response2 = $this->runApp('GET', '/api/app/v2/main/123', null, $headers);
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testMainV2404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v2/main/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Character not found.', $response->getReasonPhrase());
    }

    public function testMainV1204()
    {
        $this->setUpDb();
        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $char->setMain(false);
        $this->helper->getEm()->flush();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/main/123', null, $headers);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testMainV1200()
    {
        $this->setUpDb();
        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $this->helper->addCharacterToPlayer('C2', 456, $char->getPlayer());

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
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
                'validToken' => null,
                'corporation' => null
            ],
            $body1
        );
    }

    public function testCharactersV1403()
    {
        $response = $this->runApp('GET', '/api/app/v1/characters/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->setUpDb();
        $headers = ['Authorization' => 'Bearer '.base64_encode($this->app0Id.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/characters/123', null, $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testCharactersV1404()
    {
        $this->setUpDb();

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response = $this->runApp('GET', '/api/app/v1/characters/123', null, $headers);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Character not found.', $response->getReasonPhrase());
    }

    public function testCharactersV1200()
    {
        $this->setUpDb();
        $char = $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $this->helper->addCharacterToPlayer('C2', 456, $char->getPlayer());

        $headers = ['Authorization' => 'Bearer '.base64_encode($this->appId.':s1')];
        $response1 = $this->runApp('GET', '/api/app/v1/characters/123', null, $headers);
        $response2 = $this->runApp('GET', '/api/app/v1/characters/456', null, $headers);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());

        $body1 = $this->parseJsonBody($response1);
        $body2 = $this->parseJsonBody($response2);

        $this->assertSame($body1, $body2);
        $this->assertSame(
            [[
                'id' => 123,
                'name' => 'C1',
                'main' => true,
                'lastUpdate' => null,
                'validToken' => null,
                'corporation' => null
            ],[
                'id' => 456,
                'name' => 'C2',
                'main' => false,
                'lastUpdate' => null,
                'validToken' => null,
                'corporation' => null
            ]],
            $body1
        );
    }

    private function setUpDb()
    {
        $this->helper->emptyDb();
        $this->app0Id = $this->helper->addApp('A0', 's0', [Role::APP])->getId();
        $this->appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_CHARS])->getId();
    }
}
