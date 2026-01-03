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
use Neucore\Storage\StorageDatabaseInterface;
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

    private StorageDatabaseInterface $storage;

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
        $timeOffset = 34;
        $time = time() - $timeOffset;
        $charId = 1234;

        self::assertSame(0, EsiClient::getRateLimitWaitTime(
            $this->storage,
            "/characters/$charId/roles",
            'GET',
            $charId,
            20,
        ));

        $this->storage->set(Variables::ESI_RATE_LIMIT, EsiRateLimit::toJson([
            "char-detail:$charId" => new EsiRateLimit('char-detail', '600/15m', 148, 2, $time),
        ]));
        self::assertSame(0, EsiClient::getRateLimitWaitTime(
            $this->storage,
            "/characters/$charId/roles",
            'GET',
            $charId,
            20,
        ));

        // All tokens used
        $this->storage->set(Variables::ESI_RATE_LIMIT, EsiRateLimit::toJson([
            "char-detail:$charId" => new EsiRateLimit('char-detail', '600/15m', 1, 2, $time),
        ]));
        $actual1 = EsiClient::getRateLimitWaitTime(
            $this->storage,
            "/characters/$charId/roles",
            'GET',
            $charId,
            20,
        );
        self::assertGreaterThanOrEqual(time() + 60, $actual1);
        self::assertLessThan(time() + 60 + 2, $actual1);

        // All allowed tokens for the proxy used.
        $this->storage->set(Variables::ESI_RATE_LIMIT, EsiRateLimit::toJson([
            "char-detail:$charId" => new EsiRateLimit('char-detail', '600/15m', 100, 2, $time),
        ]));
        $actual2 = EsiClient::getRateLimitWaitTime(
            $this->storage,
            "/characters/$charId/roles",
            'GET',
            $charId,
            20,
        );
        self::assertGreaterThanOrEqual(time() + $timeOffset, $actual2);
        self::assertLessThan(time() + $timeOffset + 2, $actual2);
    }

    public function testGetRateLimitedWaitTime(): void
    {
        $time = time();

        self::assertSame(0, EsiClient::getRateLimitedWaitTime($this->storage));

        $this->storage->set(Variables::ESI_RATE_LIMITED, (string) ($time + 50));
        self::assertSame($time + 50, EsiClient::getRateLimitedWaitTime($this->storage));
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

        $rateLimits = ['test:123' => new EsiRateLimit('fitting', '150/15m', 148, 2, 1767448553)];
        $this->storage->set(Variables::ESI_RATE_LIMIT, EsiRateLimit::toJson($rateLimits));
        self::assertEquals(
            $rateLimits,
            EsiClient::getRateLimits($this->storage),
        );
    }

    public function testIsPublicPath(): void
    {
        self::assertTrue(EsiClient::isPublicPath('/characters/123456789'));
        self::assertTrue(EsiClient::isPublicPath('/characters/123456789/'));
        self::assertTrue(EsiClient::isPublicPath('/characters/123456789?'));
        self::assertTrue(EsiClient::isPublicPath('/characters/123456789/?'));
        self::assertTrue(EsiClient::isPublicPath('/characters/123456789?a=b'));
        self::assertTrue(EsiClient::isPublicPath('/characters/123456789/?a=b'));
        self::assertFalse(EsiClient::isPublicPath('/characters/123456789/assets'));
        self::assertFalse(EsiClient::isPublicPath('/invalid'));
    }

    public function testGetRateLimitGroup(): void
    {
        self::assertNull(EsiClient::getRateLimitGroup('/invalid', 'GET'));
        self::assertSame(
            'char-detail',
            EsiClient::getRateLimitGroup('/characters/2120311950/roles?a=b', 'GET'),
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
