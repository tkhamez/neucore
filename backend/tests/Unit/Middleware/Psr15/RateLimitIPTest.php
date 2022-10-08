<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Middleware\Psr15\RateLimit;
use Neucore\Middleware\Psr15\RateLimitIP;
use Neucore\Service\Config;
use Neucore\Storage\ApcuStorage;
use Neucore\Storage\Variables;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
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
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode(self::$appIdp.':abc');
        self::$key = Variables::RATE_LIMIT_IP . '_' . str_replace('.', '', self::$ip);
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
        $storage->set(self::$key, (string)\json_encode((object)['remaining' => 0, 'created' => time() - 5]));

        $logger = new Logger('Test');
        $request = RequestFactory::createRequest();

        $config1 = new Config(['rate_limit' => ['max' => 0, 'time' => 0]]);
        $middleware1 = new RateLimitIP($storage, $config1, new ResponseFactory(), $logger);
        $response1 = $middleware1->process($request, new RequestHandler());
        $this->assertSame(200, $response1->getStatusCode());

        $config2 = new Config(['rate_limit' => ['max' => 50, 'time' => 0]]);
        $middleware2 = new RateLimitIP($storage, $config2, new ResponseFactory(), $logger);
        $response2 = $middleware2->process($request, new RequestHandler());
        $this->assertSame(200, $response2->getStatusCode());

        $config3 = new Config(['rate_limit' => ['max' => 0, 'time' => 10]]);
        $middleware3 = new RateLimitIP($storage, $config3, new ResponseFactory(), $logger);
        $response3 = $middleware3->process($request, new RequestHandler());
        $this->assertSame(200, $response3->getStatusCode());
    }

    public function testProcess_Active()
    {
        $storage = new ApcuStorage();
        $storage->set(self::$key, (string)\json_encode((object)['remaining' => 0, 'created' => time() - 5]));

        $logger = new Logger('Test');
        $request = RequestFactory::createRequest();

        $config = new Config(['rate_limit' => ['max' => 50, 'time' => 10]]);
        $middleware = new RateLimitIP($storage, $config, new ResponseFactory(), $logger);
        $response = $middleware->process($request, new RequestHandler());
        $this->assertSame(429, $response->getStatusCode());

        $this->assertSame('-1', $response->getHeader(RateLimit::HEADER_REMAIN)[0]);
        $this->assertEqualsWithDelta(4.5, $response->getHeader(RateLimit::HEADER_RESET)[0], 1.0);
        $this->assertStringStartsWith(
            'IP rate limit exceeded with 51 requests in ', // ... ~5.5 seconds
            $response->getBody()->__toString()
        );

        $logs = $logger->getHandler()->getRecords();
        $this->assertSame(1, count($logs));
        $this->assertStringStartsWith(
            'IP Rate Limit: '.self::$ip.', App-ID '.self::$appIdp.', '.
                'limit exceeded with 51 request in ', // ... ~5.5 seconds.
            $logs[0]['message']
        );
    }
}
