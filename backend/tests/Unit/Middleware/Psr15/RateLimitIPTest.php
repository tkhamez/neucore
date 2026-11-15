<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Application;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Psr15\RateLimit;
use Neucore\Middleware\Psr15\RateLimitIP;
use Neucore\Service\Config;
use Neucore\Service\ObjectManager;
use Neucore\Service\RateLimitState;
use Neucore\Storage\ApcuStorage;
use Neucore\Storage\DatabaseStorage;
use Neucore\Storage\MemcachedStorage;
use Neucore\Storage\Variables;
use Neucore\Util\Http;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\Helper;
use Tests\Logger;
use Tests\RequestFactory;
use Tests\RequestHandler;

class RateLimitIPTest extends TestCase
{
    private static string $ip = '127.0.0.1';

    private static string $appIdp = '7';

    private static string $key;

    public static function setUpBeforeClass(): void
    {
        RateLimitIP::$active = true;

        $_SERVER['REMOTE_ADDR'] = self::$ip;
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode(self::$appIdp . ':abc');
        $ipBinary = inet_pton(self::$ip);
        self::assertIsString($ipBinary);
        self::$key = Variables::RATE_LIMIT_IP . '_' . bin2hex($ipBinary);
    }

    public static function tearDownAfterClass(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        RateLimitIP::$active = false;
    }

    public function testProcess_Inactive()
    {
        $storage = new ApcuStorage();
        $storage->set(self::$key, (string) \json_encode((object) ['remaining' => 0, 'created' => time() - 5]));

        $logger = new Logger();
        $request = RequestFactory::createRequest();

        $config1 = new Config(['rate_limit' => ['max' => 0, 'time' => 0]]);
        $middleware1 = new RateLimitIP($storage, $config1, new ResponseFactory(), $logger, new RateLimitState());
        $response1 = $middleware1->process($request, new RequestHandler());
        $this->assertSame(200, $response1->getStatusCode());

        $config2 = new Config(['rate_limit' => ['max' => 50, 'time' => 0]]);
        $middleware2 = new RateLimitIP($storage, $config2, new ResponseFactory(), $logger, new RateLimitState());
        $response2 = $middleware2->process($request, new RequestHandler());
        $this->assertSame(200, $response2->getStatusCode());

        $config3 = new Config(['rate_limit' => ['max' => 0, 'time' => 10]]);
        $middleware3 = new RateLimitIP($storage, $config3, new ResponseFactory(), $logger, new RateLimitState());
        $response3 = $middleware3->process($request, new RequestHandler());
        $this->assertSame(200, $response3->getStatusCode());
    }

    public function testProcess_Active()
    {
        $storage = new ApcuStorage();
        $storage->set(self::$key, (string) \json_encode((object) ['remaining' => 0, 'created' => time() - 5]));

        $logger = new Logger();
        $request = RequestFactory::createRequest();

        $config = new Config(['rate_limit' => ['max' => 50, 'time' => 10]]);
        $middleware = new RateLimitIP($storage, $config, new ResponseFactory(), $logger, new RateLimitState());
        $response = $middleware->process($request, new RequestHandler());
        $this->assertSame(429, $response->getStatusCode());

        $this->assertSame('-1', $response->getHeader(RateLimit::HEADER_REMAIN)[0]);
        $this->assertEqualsWithDelta(4.5, $response->getHeader(RateLimit::HEADER_RESET)[0], 1.0);
        $this->assertStringStartsWith(
            'IP rate limit exceeded with 51 requests in ', // ... ~5.5 seconds
            $response->getBody()->__toString(),
        );

        $logs = $logger->getMessages();
        $this->assertSame(1, count($logs));
        $this->assertStringStartsWith(
            'IP Rate Limit: ' . self::$ip . ', App-ID ' . self::$appIdp . ', '
                . 'limit exceeded with 51 request in ', // ... ~5.5 seconds.
            $logs[0],
        );
    }

    public function testProcess_UnknownIp_UsesSharedBucket(): void
    {
        $storage = new ApcuStorage();
        $logger = new Logger();
        $request = RequestFactory::createRequest();

        $unknownKey = Variables::RATE_LIMIT_IP . '_' . Http::IP_UNKNOWN;

        $config = new Config(['rate_limit' => ['max' => 5, 'time' => 10]]);
        $middleware = new RateLimitIP($storage, $config, new ResponseFactory(), $logger, new RateLimitState());

        $remoteAddr = $_SERVER['REMOTE_ADDR'];
        $_SERVER['REMOTE_ADDR'] = '';

        // all unknown IPs are treated as the same IP address: they share a single bucket
        // with the normal limit
        $response1 = $middleware->process($request, new RequestHandler());
        $this->assertSame(200, $response1->getStatusCode());

        $storage->set($unknownKey, (string) \json_encode((object) ['remaining' => 0, 'created' => time()]));
        $response2 = $middleware->process($request, new RequestHandler());
        $_SERVER['REMOTE_ADDR'] = $remoteAddr;
        $this->assertSame(429, $response2->getStatusCode());
        $this->assertStringContainsString('6 requests', $response2->getBody()->__toString());

        $logs = $logger->getMessages();
        $this->assertSame(1, count($logs));
        $this->assertStringStartsWith(
            'IP Rate Limit: ' . Http::IP_UNKNOWN . ', App-ID ' . self::$appIdp . ', limit exceeded with 6 request in ',
            $logs[0],
        );
    }

    public function testProcess_IpKey_CanonicalForm(): void
    {
        $storage = new ApcuStorage();
        $logger = new Logger();
        $request = RequestFactory::createRequest();

        $config = new Config(['rate_limit' => ['max' => 50, 'time' => 10]]);
        $middleware = new RateLimitIP($storage, $config, new ResponseFactory(), $logger, new RateLimitState());

        $remoteAddr = $_SERVER['REMOTE_ADDR'];

        // an IPv4-mapped IPv6 address uses the same bucket as the plain IPv4 address
        $storage->set(self::$key, (string) \json_encode((object) ['remaining' => 0, 'created' => time()]));
        $_SERVER['REMOTE_ADDR'] = '::ffff:' . self::$ip;
        $response1 = $middleware->process($request, new RequestHandler());
        $this->assertSame(429, $response1->getStatusCode());

        // an IPv6 address is keyed by its binary form, independent of the compression format
        $ipv6Binary = inet_pton('2001:db8::2');
        self::assertIsString($ipv6Binary);
        $ipv6Key = Variables::RATE_LIMIT_IP . '_' . bin2hex($ipv6Binary);
        $storage->set($ipv6Key, (string) \json_encode((object) ['remaining' => 0, 'created' => time()]));
        $_SERVER['REMOTE_ADDR'] = '2001:0db8:0000:0000:0000:0000:0000:0002';
        $response2 = $middleware->process($request, new RequestHandler());
        $_SERVER['REMOTE_ADDR'] = $remoteAddr;
        $this->assertSame(429, $response2->getStatusCode());
    }

    public function testProcess_DatabaseStorage_IPLimitSkipped(): void
    {
        $om = (new Helper())->getObjectManager();
        $logger = new Logger();
        $storage = new DatabaseStorage(new RepositoryFactory($om), new ObjectManager($om, $logger));

        // an exhausted limit is ignored, the IP rate limit needs a fast storage driver
        $storage->set(self::$key, (string) \json_encode((object) ['remaining' => 0, 'created' => time()]));

        $config = new Config(['rate_limit' => ['max' => 50, 'time' => 10]]);
        $middleware = new RateLimitIP($storage, $config, new ResponseFactory(), $logger, new RateLimitState());
        $response = $middleware->process(RequestFactory::createRequest(), new RequestHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testProcess_MemcachedStorage(): void
    {
        $config = (new Application())->loadSettings(true);
        $server = $config['memcached']['server'];
        if (!extension_loaded('memcached') || !str_contains($server, ':')) {
            self::markTestSkipped('Memcached not available or missing server configuration.');
        }

        $logger = new Logger();

        [$host, $port] = explode(':', $server);
        /** @noinspection PhpComposerExtensionStubsInspection */
        $memcached = new \Memcached();
        $memcached->addServer($host, (int) $port);
        $storage = new MemcachedStorage($memcached, $logger);

        $request = RequestFactory::createRequest();

        try {
            $storage->set(self::$key, (string) \json_encode((object) ['remaining' => 0, 'created' => time()]));

            $config = new Config(['rate_limit' => ['max' => 50, 'time' => 10]]);
            $middleware = new RateLimitIP($storage, $config, new ResponseFactory(), $logger, new RateLimitState());
            $response = $middleware->process($request, new RequestHandler());
            $this->assertSame(429, $response->getStatusCode());
        } finally {
            $memcached->delete(MemcachedStorage::PREFIX . self::$key);
        }
    }

    public function testProcess_StorageFails_FailOpen(): void
    {
        if (!extension_loaded('memcached')) {
            self::markTestSkipped('Memcached extension not available.');
        }

        $logger = new Logger();

        // An unreachable Memcached server: the counter update fails and the request passes (fail-open)
        /** @noinspection PhpComposerExtensionStubsInspection */
        $memcached = new \Memcached();
        $memcached->addServer('127.0.0.1', 1);
        $storage = new MemcachedStorage($memcached, $logger);

        $config = new Config(['rate_limit' => ['max' => 50, 'time' => 10]]);
        $middleware = new RateLimitIP($storage, $config, new ResponseFactory(), $logger, new RateLimitState());
        $response = $middleware->process(RequestFactory::createRequest(), new RequestHandler());

        $this->assertSame(200, $response->getStatusCode());
        $logs = $logger->getMessages();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString(
            'IP Rate Limit (127.0.0.1): Failed to consume the rate limit counter (2).',
            $logs[0],
        );
    }
}
