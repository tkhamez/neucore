<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Entity\Role;
use GuzzleHttp\ClientInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Client;

class EsiControllerTest extends WebTestCase
{
    public function setUp()
    {
        $_SESSION = [];
    }

    public function testRequest403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/esi/request');
        $this->assertEquals(403, $response1->getStatusCode());

        $this->loginUser(6);

        $response2 = $this->runApp('GET', '/api/user/esi/request');
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testRequest400()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('GET', '/api/user/esi/request');
        $this->assertEquals(400, $response1->getStatusCode());
        $this->assertEquals('Missing route and/or character parameter.', $this->parseJsonBody($response1));

        $response2 = $this->runApp(
            'GET',
            '/api/user/esi/request?character=123&route=/characters/{character_id}'
        );
        $this->assertEquals(400, $response2->getStatusCode());
        $this->assertEquals('Character not found.', $this->parseJsonBody($response2));
    }

    public function testRequest200()
    {
        $this->setupDb();
        $this->loginUser(7);

        $httpClient = new Client();
        $httpClient->setResponse(new \GuzzleHttp\Psr7\Response(
            200,
            [
                'X-Esi-Error-Limit-Remain' => [100],
                'X-Esi-Error-Limit-Reset' => [60],
                'Expires' => ['Sat, 02 Mar 2019 12:26:53 GMT'],
            ],
            '{"key": "value"}'
        ));

        $response = $this->runApp(
            'GET',
            '/api/user/esi/request?character=6&route=/characters/{character_id}',
            null,
            null,
            [ClientInterface::class => $httpClient]
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([
            'headers' => [
                'X-Esi-Error-Limit-Remain' => '100',
                'X-Esi-Error-Limit-Reset' => '60',
                'X-Pages' => null,
                'warning' => null,
                'Expires' => 'Sat, 02 Mar 2019 12:26:53 GMT',
            ],
            'body' => ['key' => 'value']
        ], $this->parseJsonBody($response));
    }

    private function setupDb()
    {
        $helper = new Helper();
        $helper->emptyDb();

        $helper->addCharacterMain('Admin', 6, [Role::USER_ADMIN]);
        $helper->addCharacterMain('Esi', 7, [Role::ESI]);
    }
}
