<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use GuzzleHttp\Psr7\Response;
use Neucore\Entity\Role;
use Neucore\Factory\HttpClientFactoryInterface;
use Tests\Functional\WebTestCase;
use Tests\Helper;
use Tests\Client;
use Tests\HttpClientFactory;

class EsiControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testRequest403()
    {
        $this->setupDb();

        $response1 = $this->runApp('GET', '/api/user/esi/request');
        $this->assertSame(403, $response1->getStatusCode());

        $this->loginUser(6);

        $response2 = $this->runApp('GET', '/api/user/esi/request');
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testRequest400_MissingParameter()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('GET', '/api/user/esi/request');
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Missing route and/or character parameter.', $this->parseJsonBody($response));
    }

    public function testRequest400_CharacterNotFound()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp(
            'GET',
            '/api/user/esi/request?character=123&route=/characters/{character_id}',
        );
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Character not found.', $this->parseJsonBody($response));
    }

    public function testRequest400_MissingToken()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp(
            'GET',
            '/api/user/esi/request?character=6&route=/characters/{character_id}&login=test-1',
        );
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('"Character has no valid token."', $response->getBody()->__toString());
    }

    public function testRequest200()
    {
        $this->setupDb();
        $this->loginUser(7);

        $httpClient = new Client();
        $httpClient->setResponse(new Response(
            200,
            [
                'X-Esi-Error-Limit-Remain' => ['100'],
                'X-Esi-Error-Limit-Reset' => ['60'],
                'Expires' => ['Sat, 02 Mar 2019 12:26:53 GMT'],
            ],
            '{"key": "value"}',
        ));

        $response = $this->runApp(
            'GET',
            '/api/user/esi/request?character=6&route=/characters/{character_id}',
            null,
            null,
            [HttpClientFactoryInterface::class => new HttpClientFactory($httpClient)],
        );
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'headers' => [
                ['Expires', 'Sat, 02 Mar 2019 12:26:53 GMT'],
                ['X-Esi-Error-Limit-Remain', '100'],
                ['X-Esi-Error-Limit-Reset', '60'],
            ],
            'body' => ['key' => 'value'],
        ], $this->parseJsonBody($response));
    }

    public function testRequestPost200(): void
    {
        $this->setupDb();
        $this->loginUser(7);

        $httpClient = new Client();
        $httpClient->setResponse(new Response(200, [], '{
            "alliance_id": 99003214,
            "character_id": 96061222,
            "corporation_id": 98522659
        }'));

        $response = $this->runApp(
            'POST',
            '/api/user/esi/request?character=6&route=/characters/affiliation/',
            ['body' => [96061222]],
            null,
            [HttpClientFactoryInterface::class => new HttpClientFactory($httpClient)],
        );
        $this->assertSame(200, $response?->getStatusCode());
        $this->assertSame([
            'headers' => [],
            'body' => [
                "alliance_id" => 99003214,
                "character_id" => 96061222,
                "corporation_id" => 98522659,
            ],
        ], $this->parseJsonBody($response));
    }

    private function setupDb(): void
    {
        $helper = new Helper();
        $helper->emptyDb();

        $helper->addCharacterMain('Admin', 6, [Role::USER_ADMIN], [], true, null, 123456, true);
        $helper->addCharacterMain('Esi', 7, [Role::ESI], [], true, null, 123456, true);
    }
}
