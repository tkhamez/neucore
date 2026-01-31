<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Guzzle;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neucore\Factory\HttpClientFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\EsiRateLimits;
use Neucore\Service\EsiClient;
use Neucore\Service\ObjectManager;
use Neucore\Storage\DatabaseStorage;
use Neucore\Storage\Variables;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class EsiRateLimitsTest extends TestCase
{
    private Helper $helper;

    private DatabaseStorage $storage;

    private EsiRateLimits $obj;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $om = $this->helper->getObjectManager();

        $logger = new Logger();

        $this->storage = new DatabaseStorage(
            new RepositoryFactory($om),
            new ObjectManager($om, $logger),
        );
        #apcu_clear_cache();
        #$this->storage = new \Neucore\Storage\ApcuStorage();

        $this->obj = new EsiRateLimits($logger, $this->storage);
    }

    public function testInvoke_RateLimits(): void
    {
        $response = new Response(200, [
            EsiClient::HEADER_RATE_LIMIT_GROUP => ['char-location'],
            EsiClient::HEADER_RATE_LIMIT_LIMIT => ['1200/15m'],
            EsiClient::HEADER_RATE_LIMIT_REMAINING => ['1198'],
            EsiClient::HEADER_RATE_LIMIT_USED => ['2'],
        ]);
        $function = $this->obj->__invoke($this->helper->getGuzzleHandler($response));

        $function(
            new Request('GET', 'http://localhost/path'),
            [HttpClientFactory::HTTP_CLIENT_OPTIONS_KEY => ['character_id' => 123456]],
        );

        $actual = (string) $this->storage->get(Variables::ESI_RATE_LIMIT);
        self::assertSame(
            '{"char-location:123456":' .
            '{"g":"char-location","l":"1200/15m","r":1198,"u":2,"t":1767448553}}',
            preg_replace('/"t":\d+/', '"t":1767448553', $actual),
        );
        preg_match('/"t":(\d+)/', $actual, $actualTime);
        self::assertLessThanOrEqual(time(), $actualTime[1]);
    }

    public function testInvoke_429(): void
    {
        $waitUntil = time() + 60;
        $response1 = new Response(429, ['Retry-After' => [date('D, d M Y H:i:s \G\M\T', $waitUntil)]]);
        $function1 = $this->obj->__invoke($this->helper->getGuzzleHandler($response1));
        $function1(new Request('GET', 'https://local.host/esi/path'), []);
        self::assertSame("$waitUntil", $this->storage->get(Variables::ESI_RATE_LIMITED));

        $response2 = new Response(429, ['Retry-After' => ['60']]);
        $function2 = $this->obj->__invoke($this->helper->getGuzzleHandler($response2));
        $function2(new Request('GET', 'https://local.host/esi/path'), []);
        self::assertIsString($this->storage->get(Variables::ESI_RATE_LIMITED));
        self::assertLessThanOrEqual(time() + 60, $this->storage->get(Variables::ESI_RATE_LIMITED));
    }
}
