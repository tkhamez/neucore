<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use Monolog\Logger;
use Neucore\Factory\HttpClientFactory;
use Neucore\Middleware\Guzzle\Esi429Response;
use Neucore\Middleware\Guzzle\EsiHeaders;
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
            new Esi429Response($logger, new ApcuStorage()),
            $logger,
        );
    }

    public function testGet()
    {
        $this->assertFalse(is_dir(__DIR__ . '/cache-key'));

        $actual = $this->factory->get('cache-key');

        $this->assertInstanceOf(ClientInterface::class, $actual);
        $this->assertTrue(is_dir(__DIR__ . '/cache-key'));
    }

    public function testGetGuzzleClient()
    {
        $actual = $this->factory->getGuzzleClient(null);
        $this->assertInstanceOf(\GuzzleHttp\ClientInterface::class, $actual);
        $this->assertFalse(is_dir(__DIR__ . '/cache-key'));
    }

    public function testCreateRequest()
    {
        $actual = $this->factory->createRequest('GET', 'http://localhost', ['X-Header-Name' => 'value'], 'body');
        $this->assertInstanceOf(RequestInterface::class, $actual);
        $this->assertSame('GET', $actual->getMethod());
        $this->assertSame('http://localhost', $actual->getUri()->__toString());
        $this->assertSame(['value'], $actual->getHeader('X-Header-Name'));
        $this->assertSame('body', $actual->getBody()->getContents());
    }
}
