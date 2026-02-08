<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use GuzzleHttp\Psr7\Request;
use Neucore\Factory\HttpClientFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\EsiRateLimits;
use Neucore\Middleware\Guzzle\EsiErrorLimit;
use Neucore\Middleware\Guzzle\EsiThrottled;
use Neucore\Middleware\Guzzle\EsiWarnings;
use Neucore\Service\Config;
use Neucore\Service\ObjectManager;
use Neucore\Storage\DatabaseStorage;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Test\TestLogger;
use Tests\Helper;

class HttpClientFactoryTest extends TestCase
{
    private HttpClientFactory $factory;

    private TestLogger $logger;

    protected function setUp(): void
    {
        if (is_dir(__DIR__ . '/cache-key/@')) {
            rmdir(__DIR__ . '/cache-key/@');
            rmdir(__DIR__ . '/cache-key');
        }

        $h = new Helper();
        $h->emptyDb();

        $this->logger = new TestLogger();
        $em = $h->getEm();
        $storage = new DatabaseStorage(
            new RepositoryFactory($em),
            new ObjectManager($em, $this->logger),
        );

        $this->factory = new HttpClientFactory(
            new Config([
                'guzzle' => [
                    'cache' => [
                        'dir' => __DIR__,
                    ],
                    'user_agent' => 'Test',
                ],
            ]),
            new EsiErrorLimit($storage),
            new EsiWarnings($this->logger),
            new EsiRateLimits($this->logger, $storage),
            new EsiThrottled($storage),
            $this->logger,
            0,
        );
    }

    public function testGet_FilesystemCache(): void
    {
        $this->assertFalse(is_dir(__DIR__ . '/cache-key'));

        $this->factory->get('cache-key');
        $this->assertTrue(is_dir(__DIR__ . '/cache-key'));
    }

    public function testGetGuzzleClient_FilesystemCache(): void
    {
        $this->factory->getGuzzleClient(null);
        $this->assertFalse(is_dir(__DIR__ . '/cache-key'));
    }

    public function testGet_RetryMiddleware_ConnectionFailed(): void
    {
        $client = $this->factory->get('cache-key');

        try {
            $client->sendRequest(new Request('GET', 'http://does-not-exist-734/'));
        } catch (ClientExceptionInterface) {}

        self::assertSame(
            [
                'Retrying (1) http://does-not-exist-734/',
                'Retrying (2) http://does-not-exist-734/',
            ],
            $this->logger->getMessages(),
        );
    }
}
