<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Functional\Controller\App;

use GuzzleHttp\Exception\TransferException;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Role;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\EsiHeaders;
use GuzzleHttp\Psr7\Response;
use Neucore\Service\ObjectManager;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Neucore\Storage\SystemVariableStorage;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\HttpClientFactory;
use Tests\Logger;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class EsiControllerTest extends WebTestCase
{
    private Helper $helper;

    private Logger $logger;

    private SystemVariableStorage $storage;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $om = $this->helper->getObjectManager();
        $this->logger = new Logger('test');

        $this->storage = new SystemVariableStorage(new RepositoryFactory($om), new ObjectManager($om, $this->logger));
        #apcu_clear_cache();
        #$this->storage = new \Neucore\Storage\ApcuStorage();
    }

    public function testEveLoginCharacters403()
    {
        // no app
        $response1 = $this->runApp('GET', '/api/app/v1/esi/eve-login/name/characters');
        $this->assertSame(403, $response1->getStatusCode());

        // "core.default" is not allowed
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($appId.':s1')];
        $response2 = $this->runApp('GET', '/api/app/v1/esi/eve-login/core.default/characters', null, $headers);
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testEveLoginCharacters404()
    {
        $app = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI]);
        $headers = ['Authorization' => 'Bearer '.base64_encode($app->getId().':s1')];

        // login does not exist
        $response = $this->runApp('GET', '/api/app/v1/esi/eve-login/name1/characters', null, $headers);
        $this->assertSame(404, $response->getStatusCode());

        // app may not use the login
        $eveLogin = (new EveLogin())->setName('test-1');
        $this->helper->getEm()->persist($eveLogin);
        $this->helper->getEm()->flush();
        $response = $this->runApp('GET', '/api/app/v1/esi/eve-login/test-1/characters', null, $headers);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testEveLoginCharacters200()
    {
        $eveLogin = (new EveLogin())->setName('test-1');
        $this->helper->getEm()->persist($eveLogin);
        $app = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI]);
        $app->addEveLogin($eveLogin);
        $character = $this->helper->addCharacterMain('Char 1', 123456);
        $esiToken = (new EsiToken())->setEveLogin($eveLogin)->setCharacter($character)
            ->setRefreshToken('rt')->setAccessToken('at')->setExpires(0);
        $this->helper->getEm()->persist($esiToken);
        $this->helper->getEm()->flush();
        $this->helper->getEm()->clear();

        $headers = ['Authorization' => 'Bearer '.base64_encode($app->getId().':s1')];
        $response = $this->runApp('GET', '/api/app/v1/esi/eve-login/test-1/characters', null, $headers);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([123456], $this->parseJsonBody($response));
    }

    public function testEsiV1403()
    {
        $response1 = $this->runApp('GET', '/api/app/v1/esi');
        $this->assertSame(403, $response1->getStatusCode());

        $appId = $this->helper->addApp('A1', 's1', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($appId.':s1')];

        $response2 = $this->runApp('GET', '/api/app/v1/esi', null, $headers);
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testEsiV2403()
    {
        $response1 = $this->runApp('GET', '/api/app/v2/esi');
        $this->assertSame(403, $response1->getStatusCode());

        $appId = $this->helper->addApp('A1', 's1', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($appId.':s1')];

        $response2 = $this->runApp('GET', '/api/app/v2/esi', null, $headers);
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testEsiV1400_MissingParameters_ReasonPhrase()
    {
        // Only test the reason phrase, rest is in v2 test.

        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($appId.':s1')];

        $response1 = $this->runApp('GET', '/api/app/v1/esi', null, $headers);
        $this->assertSame(400, $response1->getStatusCode());
        $this->assertSame('Path cannot be empty.', $response1->getReasonPhrase());
    }

    public function testEsiV2400_MissingParameters()
    {
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($appId.':s1')];

        $response1 = $this->runApp('GET', '/api/app/v2/esi', null, $headers);
        $this->assertSame(400, $response1->getStatusCode());
        $this->assertSame('"Path cannot be empty."', $response1->getBody()->__toString());

        $response2 = $this->runApp(
            'GET',
            '/api/app/v2/esi/latest/characters/96061222/stats/',
            null,
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')]
        );
        $this->assertSame(400, $response2->getStatusCode());
        $this->assertSame(
            '"The datasource parameter cannot be empty, it must contain an EVE character ID"',
            $response2->getBody()->__toString()
        );
    }

    public function testEsiV2400_PublicRoute()
    {
        // No need to test this with v1 route, the only difference is tested with
        // testEsiV1400_MissingParameters_ReasonPhrase().

        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($appId.':s1')];

        $response1 = $this->runApp('GET', '/api/app/v2/esi/latest/alliances/', null, $headers);
        $this->assertSame(400, $response1->getStatusCode());
        $this->assertSame('"Public ESI routes are not allowed."', $response1->getBody()->__toString());

        $response2 = $this->runApp('GET', '/api/app/v2/esi/latest/alliances/123456/icons/', null, $headers);
        $this->assertSame(400, $response2->getStatusCode());
        $this->assertSame('"Public ESI routes are not allowed."', $response2->getBody()->__toString());

        $response3 = $this->runApp('GET', '/api/app/v2/esi/latest/killmails/123456/123abc/', null, $headers);
        $this->assertSame(400, $response3->getStatusCode());
        $this->assertSame('"Public ESI routes are not allowed."', $response3->getBody()->__toString());
    }

    public function testEsiV1400_CharacterNotFound()
    {
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $response = $this->runApp(
            'GET',
            '/api/app/v1/esi/latest/characters/96061222/stats/?datasource=96061222',
            null,
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')]
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Character not found.', $response->getReasonPhrase());
    }

    public function testEsiV2400_CharacterNotFound()
    {
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $response = $this->runApp(
            'GET',
            '/api/app/v2/esi/latest/characters/96061222/stats/?datasource=96061222',
            null,
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')]
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('"Character not found."', $response->getBody()->__toString());
    }

    public function testEsiV1400_MissingToken()
    {
        $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $response = $this->runApp(
            'GET',
            '/api/app/v1/esi/latest/characters/96061222/stats/?datasource=123%3Atest-1',
            null,
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')]
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Character has no valid token.', $response->getReasonPhrase());
    }

    public function testEsiV2400_MissingToken()
    {
        $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $response = $this->runApp(
            'GET',
            '/api/app/v2/esi/latest/characters/96061222/stats/?datasource=123%3Atest-1',
            null,
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')]
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('"Character has no valid token."', $response->getBody()->__toString());
    }

    public function testEsiV1429()
    {
        // Only test the reason phrase, rest is in v2 test.

        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        // add var
        $this->storage->set(
            Variables::ESI_ERROR_LIMIT,
            (string) \json_encode(['updated' => time(), 'remain' => 20, 'reset' => 86])
        );

        $response = $this->runApp(
            'GET',
            '/api/app/v1/esi',
            [],
            ['Authorization' => 'Bearer ' . base64_encode($appId . ':s1')],
            [LoggerInterface::class => $this->logger, StorageInterface::class => $this->storage]
        );

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame([], $response->getHeader('Retry-After'));
        $this->assertSame('Maximum permissible ESI error limit reached.', $response->getReasonPhrase());
    }

    public function testEsiV2429_ErrorLimit()
    {
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        // add var
        $this->storage->set(
            Variables::ESI_ERROR_LIMIT,
            (string) \json_encode(['updated' => time(), 'remain' => 20, 'reset' => 86])
        );

        $response = $this->runApp(
            'GET',
            '/api/app/v2/esi',
            [],
            ['Authorization' => 'Bearer ' . base64_encode($appId . ':s1')],
            [LoggerInterface::class => $this->logger, StorageInterface::class => $this->storage]
        );

        $this->assertSame(429, $response->getStatusCode());
        $this->assertGreaterThan('84', $response->getHeaderLine('Retry-After'));
        $this->assertLessThanOrEqual('86', $response->getHeaderLine('Retry-After'));
        $this->assertSame(
            '"Maximum permissible ESI error limit reached (X-Esi-Error-Limit-Remain <= 20)."',
            $response->getBody()->__toString()
        );
        $this->assertSame(
            'App\EsiController: application ' . $appId .
            ' "A1": Maximum permissible ESI error limit reached (X-Esi-Error-Limit-Remain <= 20).',
            $this->logger->getHandler()->getRecords()[0]['message']
        );
    }

    public function testEsiV1429_NotReached()
    {
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        // add var
        $this->storage->set(
            Variables::ESI_ERROR_LIMIT,
            (string) \json_encode(['updated' => time(), 'remain' => 21, 'reset' => 86])
        );

        $response = $this->runApp(
            'GET',
            '/api/app/v1/esi',
            [],
            ['Authorization' => 'Bearer ' . base64_encode($appId . ':s1')],
            [StorageInterface::class => $this->storage]
        );

        $this->assertNotEquals(429, $response->getStatusCode());
    }

    public function testEsiV1429_ReachedAndReset()
    {
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        // add var
        $this->storage->set(
            Variables::ESI_ERROR_LIMIT,
            (string) \json_encode(['updated' => time() - 87, 'remain' => 20, 'reset' => 86])
        );

        $response = $this->runApp(
            'GET',
            '/api/app/v1/esi',
            [],
            ['Authorization' => 'Bearer ' . base64_encode($appId . ':s1')],
            [StorageInterface::class => $this->storage]
        );

        $this->assertNotEquals(429, $response->getStatusCode());
    }

    public function testEsiV2429_RateLimit()
    {
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $this->storage->set(Variables::ESI_RATE_LIMIT, (string)(time() - 10));
        $response1 = $this->runApp(
            'GET',
            '/api/app/v2/esi',
            [],
            ['Authorization' => 'Bearer ' . base64_encode($appId . ':s1')]
        );
        $this->assertNotEquals(429, $response1->getStatusCode());

        $this->storage->set(Variables::ESI_RATE_LIMIT, (string)(time() + 20));
        $response2 = $this->runApp(
            'GET',
            '/api/app/v2/esi',
            [],
            ['Authorization' => 'Bearer ' . base64_encode($appId . ':s1')],
            [LoggerInterface::class => $this->logger, StorageInterface::class => $this->storage]
        );

        $this->assertSame(429, $response2->getStatusCode());
        $this->assertGreaterThan('18', $response2->getHeaderLine('Retry-After'));
        $this->assertLessThanOrEqual('20', $response2->getHeaderLine('Retry-After'));
        $errorMessage = 'ESI rate limit reached.';
        $this->assertSame(json_encode($errorMessage), $response2->getBody()->__toString());
        $this->assertSame(
            "App\EsiController: application $appId \"A1\": $errorMessage",
            $this->logger->getHandler()->getRecords()[0]['message']
        );
    }

    public function testEsiV2429_Throttled()
    {
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $this->storage->set(Variables::ESI_THROTTLED, (string)(time() - 5));
        $response1 = $this->runApp(
            'GET',
            '/api/app/v2/esi',
            [],
            ['Authorization' => 'Bearer ' . base64_encode($appId . ':s1')]
        );
        $this->assertNotEquals(429, $response1->getStatusCode());

        $this->storage->set(Variables::ESI_THROTTLED, (string)(time() + 5));
        $response2 = $this->runApp(
            'GET',
            '/api/app/v2/esi',
            [],
            ['Authorization' => 'Bearer ' . base64_encode($appId . ':s1')],
            [LoggerInterface::class => $this->logger, StorageInterface::class => $this->storage]
        );

        $this->assertSame(429, $response2->getStatusCode());
        $this->assertGreaterThan('3', $response2->getHeaderLine('Retry-After'));
        $this->assertLessThanOrEqual('5', $response2->getHeaderLine('Retry-After'));
        $errorMessage = 'Undefined 429 response. You have been temporarily throttled.';
        $this->assertSame(json_encode($errorMessage), $response2->getBody()->__toString());
        $this->assertSame(
            "App\EsiController: application $appId \"A1\": $errorMessage",
            $this->logger->getHandler()->getRecords()[0]['message']
        );
    }

    public function testEsiV1500_ClientException()
    {
        $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $httpClient = new Client();
        $httpClient->setMiddleware(function () {
            throw new TransferException("error message", 500);
        });
        $httpClient->setResponse(new Response());

        $response = $this->runApp(
            'GET',
            '/api/app/v1/esi/latest/universe/structures/1/?page=1&datasource=123:core.default',
            [],
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')],
            [
                HttpClientFactoryInterface::class => new HttpClientFactory($httpClient),
                LoggerInterface::class => $this->logger
            ]
        );

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('error message', $response->getBody()->__toString());
        $this->assertSame(
            'App\EsiController: (application ' . $appId . ' "A1"): error message',
            $this->logger->getHandler()->getRecords()[0]['message']
        );
    }

    public function testEsiV1400_EsiError()
    {
        $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $httpClient = new Client();
        $httpClient->setResponse(new Response(
            400,
            [],
            '{"error": "not a potential structure_id (id < 100000000)"}'
        ));

        $response = $this->runApp(
            'GET',
            '/api/app/v1/esi/latest/universe/structures/1/?page=1&datasource=123:core.default',
            [],
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')],
            [
                HttpClientFactoryInterface::class => new HttpClientFactory($httpClient),
                LoggerInterface::class => $this->logger
            ]
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            '{"error": "not a potential structure_id (id < 100000000)"}',
            $response->getBody()->__toString()
        );
        $this->assertSame(
            'App\EsiController: (application ' . $appId . ' "A1") ' .
            'https://esi.evetech.net/latest/universe/structures/1/?datasource=tranquility&page=1: '  .
            '{"error": "not a potential structure_id (id < 100000000)"}',
            $this->logger->getHandler()->getRecords()[0]['message']
        );
    }

    public function testEsiV1200()
    {
        $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $httpClient = new Client();
        $httpClient->setResponse(new Response(
            200,
            [
                'Content-Type' => ['application/json; charset=UTF-8'],
                'Expires' => ['Sun, 10 Feb 2019 19:22:52 GMT'],
                'X-Esi-Error-Limit-Remain' => ['100'],
                'X-Esi-Error-Limit-Reset' => ['60'],
                'X-Pages' => ['3'],
                'warning' => ['199 - This route has an upgrade available'],
            ],
            '{"key": "value"}'
        ));

        $response = $this->runApp(
            'GET',
            '/api/app/v1/esi/v3/characters/96061222/assets/?page=1&datasource=123:core.default',
            [],
            [
                'Authorization' => 'Bearer '.base64_encode($appId.':s1'),
                'If-None-Match' => '686897696a7c876b7e'
            ],
            [HttpClientFactoryInterface::class => new HttpClientFactory($httpClient)]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"key": "value"}', $response->getBody()->__toString());
        $this->assertSame([
            'Content-Type' => ['application/json; charset=UTF-8'],
            'Expires' => ['Sun, 10 Feb 2019 19:22:52 GMT'],
            'X-Esi-Error-Limit-Remain' => ['100'],
            'X-Esi-Error-Limit-Reset' => ['60'],
            'X-Pages' => ['3'],
            'warning' => ['199 - This route has an upgrade available'],
        ], $response->getHeaders());
    }

    public function testEsiV2200()
    {
        $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $httpClient = new Client();
        $httpClient->setResponse(new Response(
            200,
            [
                'Content-Type' => ['application/json; charset=UTF-8'],
                'Expires' => ['Sun, 10 Feb 2019 19:22:52 GMT'],
                'X-Esi-Error-Limit-Remain' => ['100'],
                'X-Esi-Error-Limit-Reset' => ['60'],
                'X-Pages' => ['3'],
                'warning' => ['199 - This route has an upgrade available'],
            ],
            '{"key": "value"}'
        ));

        $response = $this->runApp(
            'GET',
            '/api/app/v2/esi/v3/characters/96061222/assets/?page=1&datasource=123:core.default',
            [],
            [
                'Authorization' => 'Bearer '.base64_encode($appId.':s1'),
                'If-None-Match' => '686897696a7c876b7e'
            ],
            [HttpClientFactoryInterface::class => new HttpClientFactory($httpClient)]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"key": "value"}', $response->getBody()->__toString());
        $this->assertSame([
            'Content-Type' => ['application/json; charset=UTF-8'],
            'Expires' => ['Sun, 10 Feb 2019 19:22:52 GMT'],
            'X-Esi-Error-Limit-Remain' => ['100'],
            'X-Esi-Error-Limit-Reset' => ['60'],
            'X-Pages' => ['3'],
            'warning' => ['199 - This route has an upgrade available'],
        ], $response->getHeaders());
    }

    public function testEsiV1200Middleware()
    {
        $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        // create client with middleware
        $httpClient = new Client();
        $httpClient->setMiddleware(
            new EsiHeaders(new Logger('test'), $this->storage)
        );
        $httpClient->setResponse(new Response(
            200,
            ['X-Esi-Error-Limit-Remain' => ['100'], 'X-Esi-Error-Limit-Reset' => ['60']]
        ));

        $response = $this->runApp(
            'GET',
            '/api/app/v1/esi/v3/characters/96061222/assets/?page=1&datasource=123',
            [],
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')],
            [
                HttpClientFactoryInterface::class => new HttpClientFactory($httpClient),
                StorageInterface::class => $this->storage,
            ]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'X-Esi-Error-Limit-Remain' => ['100'],
            'X-Esi-Error-Limit-Reset' => ['60'],
        ], $response->getHeaders());

        $esiErrorVar = $this->storage->get(Variables::ESI_ERROR_LIMIT);
        $esiErrorValues = \json_decode((string) $esiErrorVar);
        $this->assertLessThanOrEqual(time(), $esiErrorValues->updated);
        $this->assertSame(100, $esiErrorValues->remain);
        $this->assertSame(60, $esiErrorValues->reset);
    }

    public function testEsiV1200PathAsParameter()
    {
        $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $httpClient = new Client();
        $httpClient->setResponse(new Response(200, [], '{"key": "value"}'));

        $response = $this->runApp(
            'GET',
            '/api/app/v1/esi?esi-path-query='. urlencode('/v3/characters/96061222/assets/?page=1') . '&datasource=123',
            null,
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')],
            [HttpClientFactoryInterface::class => new HttpClientFactory($httpClient)]
        );
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"key": "value"}', $response->getBody()->__toString());
    }

    public function testEsiPostV1403()
    {
        $response1 = $this->runApp('POST', '/api/app/v1/esi');
        $this->assertSame(403, $response1->getStatusCode());

        $appId = $this->helper->addApp('A1', 's1', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($appId.':s1')];

        $response2 = $this->runApp('POST', '/api/app/v1/esi', null, $headers);
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testEsiPostV1200()
    {
        $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $httpClient = new Client();
        $httpClient->setResponse(new Response(
            200,
            [],
            '[{ "item_id": 12345,"name": "Awesome Name" }]'
        ));

        $response = $this->runApp(
            'POST',
            '/api/app/v1/esi/v1/characters/96061222/assets/names/?datasource=123',
            [123456],
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')],
            [HttpClientFactoryInterface::class => new HttpClientFactory($httpClient)]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [['item_id' => 12345, 'name' => 'Awesome Name']],
            \json_decode($response->getBody()->__toString(), true)
        );
    }

    public function testEsiPostV2403()
    {
        $response1 = $this->runApp('POST', '/api/app/v2/esi');
        $this->assertSame(403, $response1->getStatusCode());

        $appId = $this->helper->addApp('A1', 's1', [Role::APP])->getId();
        $headers = ['Authorization' => 'Bearer '.base64_encode($appId.':s1')];

        $response2 = $this->runApp('POST', '/api/app/v2/esi', null, $headers);
        $this->assertSame(403, $response2->getStatusCode());
    }

    public function testEsiPostV2200()
    {
        $this->helper->addCharacterMain('C1', 123, [Role::USER]);
        $appId = $this->helper->addApp('A1', 's1', [Role::APP, Role::APP_ESI])->getId();

        $httpClient = new Client();
        $httpClient->setResponse(new Response(
            200,
            [],
            '[{ "item_id": 12345,"name": "Awesome Name" }]'
        ));

        $response = $this->runApp(
            'POST',
            '/api/app/v1/esi/v2/characters/96061222/assets/names/?datasource=123',
            [123456],
            ['Authorization' => 'Bearer '.base64_encode($appId.':s1')],
            [HttpClientFactoryInterface::class => new HttpClientFactory($httpClient)]
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            [['item_id' => 12345, 'name' => 'Awesome Name']],
            \json_decode($response->getBody()->__toString(), true)
        );
    }
}
