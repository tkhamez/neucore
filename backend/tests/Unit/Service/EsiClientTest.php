<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Service;

use GuzzleHttp\Psr7\Response;
use Neucore\Data\EsiErrorLimit;
use Neucore\Data\EsiRateLimit;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EsiClient;
use Neucore\Service\ObjectManager;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\SystemVariableStorage;
use Neucore\Storage\Variables;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\Logger;

class EsiClientTest extends TestCase
{
    private Helper $helper;

    private Client $httpClient;

    private EsiClient $esiClient;

    private StorageInterface $storage;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->httpClient = new Client();
        $logger = new Logger();
        $om = $this->helper->getObjectManager();
        $this->esiClient = Helper::getEsiClientService($this->httpClient, $logger, '2025-07-11');
        $this->storage = new SystemVariableStorage(new RepositoryFactory($om), new ObjectManager($om, $logger));

        $this->helper->emptyDb();
    }

    public function testGetErrorLimitWaitTime(): void
    {
        $time = time();

        self::assertSame(0, EsiClient::getErrorLimitWaitTime($this->storage, 15));

        $this->storage->set(Variables::ESI_ERROR_LIMIT, (string) json_encode(new EsiErrorLimit($time - 100, 16, 50)));
        self::assertSame(0, EsiClient::getErrorLimitWaitTime($this->storage, 15));

        $this->storage->set(Variables::ESI_ERROR_LIMIT, (string) json_encode(new EsiErrorLimit($time, 15, 50)));
        self::assertSame($time + 50, EsiClient::getErrorLimitWaitTime($this->storage, 15));

        $this->storage->set(Variables::ESI_ERROR_LIMIT, (string) json_encode(new EsiErrorLimit($time, 16, 50)));
        self::assertSame(0, EsiClient::getErrorLimitWaitTime($this->storage, 15));
    }

    public function testGetRateLimitWaitTime(): void
    {
        $time = time();

        self::assertSame(0, EsiClient::getRateLimitWaitTime($this->storage));

        $this->storage->set(Variables::ESI_RATE_LIMITED, (string) ($time + 50));
        self::assertSame($time + 50, EsiClient::getRateLimitWaitTime($this->storage));
    }

    public function testGetThrottledWaitTime(): void
    {
        $time = time();

        self::assertSame(0, EsiClient::getThrottledWaitTime($this->storage));

        $this->storage->set(Variables::ESI_THROTTLED, (string) ($time + 60));
        self::assertSame($time + 60, EsiClient::getThrottledWaitTime($this->storage));
    }

    public function testGetRateLimits(): void
    {
        self::assertSame([], EsiClient::getRateLimits($this->storage));

        $rateLimits = ['test,123' => new EsiRateLimit('fitting', '150/15m', 148, 2, 123)];
        $this->storage->set(Variables::ESI_RATE_LIMIT, EsiRateLimit::toJson($rateLimits));
        self::assertEquals(
            $rateLimits,
            EsiClient::getRateLimits($this->storage)
        );
    }

    /**
     * @throws \Throwable
     */
    public function testRequest_NoCharacter(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(568420);
        $this->expectExceptionMessage('Character not found.');

        $this->esiClient->request('/characters/102003000/', 'GET', null, 20300400);
    }

    /**
     * @throws \Throwable
     */
    public function testRequest_NoToken(): void
    {
        $this->helper->addCharacterMain('char name', 20300400, [], [], false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(568421);
        $this->expectExceptionMessage('Character has no valid token.');

        $this->esiClient->request('/characters/102003000/', 'GET', null, 20300400);
    }

    /**
     * @throws \Throwable
     */
    public function testRequest_Ok(): void
    {
        // Create char with valid, not expired, ESI token.
        $this->helper->addCharacterMain(
            'char name',
            20300400,
            tokenExpires: time() + 600,
            tokenValid: true,
        );

        $this->httpClient->setResponse(new Response(200, [], '{"name": "char name", "corporation_id": 20}'));

        $response = $this->esiClient->request(
            '/characters/102003000/',
            'GET',
            null,
            20300400,
        );

        self::assertSame(
            ['X-Compatibility-Date' => '2025-07-11', 'Accept-Language' => 'en'],
            $this->httpClient->getHeaders(),
        );
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['name' => 'char name', 'corporation_id' => 20],
            json_decode($response->getBody()->__toString(), true),
        );
    }

    /**
     * @throws \Throwable
     */
    public function testRequest_OptionalHeaders(): void
    {
        $this->helper->addCharacterMain(
            'char name',
            20300400,
            tokenExpires: time() + 600,
            tokenValid: true,
        );

        $this->httpClient->setResponse(new Response());

        $this->esiClient->request(
            '/characters/102003000/',
            'GET',
            null,
            20300400,
            compatibilityDate: '2025-07-12',
            acceptLanguage: 'de',
        );

        self::assertSame(
            [
                'X-Compatibility-Date' => '2025-07-12',
                'Accept-Language' => 'de',
            ],
            $this->httpClient->getHeaders(),
        );
    }
}
