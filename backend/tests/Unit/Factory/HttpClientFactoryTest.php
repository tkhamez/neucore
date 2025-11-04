<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use Monolog\Logger;
use Neucore\Factory\HttpClientFactory;
use Neucore\Middleware\Guzzle\EsiRateLimits;
use Neucore\Middleware\Guzzle\EsiHeaders;
use Neucore\Middleware\Guzzle\EsiThrottled;
use Neucore\Service\Config;
use Neucore\Storage\ApcuStorage;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

class HttpClientFactoryTest extends TestCase
{
    private HttpClientFactory $factory;

    protected function setUp(): void
    {
        if (is_dir(__DIR__ . '/cache-key/@')) {
            rmdir(__DIR__ . '/cache-key/@');
            rmdir(__DIR__ . '/cache-key');
        }
        $logger = new Logger('test');
        $this->factory = new HttpClientFactory(
            new Config(['guzzle' => ['cache' => ['dir' => __DIR__], 'user_agent' => 'Test']]),
            new EsiHeaders($logger, new ApcuStorage()),
            new EsiRateLimits($logger, new ApcuStorage()),
            new EsiThrottled(new ApcuStorage()),
            $logger,
        );
    }

    public function testGet(): void
    {
        $this->assertFalse(is_dir(__DIR__ . '/cache-key'));

        $this->factory->get('cache-key');
        $this->assertTrue(is_dir(__DIR__ . '/cache-key'));
    }

    public function testGetGuzzleClient(): void
    {
        $this->factory->getGuzzleClient(null);
        $this->assertFalse(is_dir(__DIR__ . '/cache-key'));
    }

    public function testCreateRequest(): void
    {
        $actual = $this->factory->createRequest('GET', 'http://localhost', ['X-Header-Name' => 'value'], 'body');
        $this->assertSame('GET', $actual->getMethod());
        $this->assertSame('http://localhost', $actual->getUri()->__toString());
        $this->assertSame(['value'], $actual->getHeader('X-Header-Name'));
        $this->assertSame('body', $actual->getBody()->getContents());
    }
}
