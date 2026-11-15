<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neucore\Factory\HttpClientFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\EsiErrorLimit;
use Neucore\Middleware\Guzzle\EsiRateLimits;
use Neucore\Middleware\Guzzle\EsiThrottled;
use Neucore\Middleware\Guzzle\EsiWarnings;
use Neucore\Service\Config;
use Neucore\Service\ObjectManager;
use Neucore\Storage\Implementation\DatabaseStorage;
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
        $this->factory = $this->createFactory();
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
        $url = 'http://127.0.0.1:1/';
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('GET', $url)),
            new ConnectException('Connection refused', new Request('GET', $url)),
            new ConnectException('Connection refused', new Request('GET', $url)),
        ]);
        $client = $this->createFactory($mock)->get(null);

        try {
            $client->sendRequest(new Request('GET', $url));
        } catch (ClientExceptionInterface) {
        }

        self::assertSame(
            [
                'Retrying (1) ' . $url,
                'Retrying (2) ' . $url,
            ],
            $this->logger->getMessages(),
        );
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function testGet_RetryMiddleware_ServerError_Retried(): void
    {
        $mock = new MockHandler([
            new Response(500),
            new Response(500),
            new Response(500),
        ]);
        $client = $this->createFactory($mock)->get(null);

        // sendRequest() forces http_errors to false (PSR-18), so the 5xx is returned as a
        // response and the retry middleware must have retried it twice.
        $response = $client->sendRequest(new Request('GET', 'http://127.0.0.1/error500'));
        self::assertSame(500, $response->getStatusCode());
        // 1 initial attempt + 2 retries => all 3 queued responses consumed
        self::assertSame(0, $mock->count());
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function testGet_RedirectNotFollowed(): void
    {
        $mock = new MockHandler([
            new Response(302, ['Location' => '/target']),
        ]);
        $client = $this->createFactory($mock)->get(null);

        // allow_redirects is false, so the 302 is returned as-is instead of being followed.
        $response = $client->sendRequest(new Request('GET', 'http://127.0.0.1/redirect'));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/target', $response->getHeaderLine('Location'));
    }

    /**
     * @throws ClientExceptionInterface
     */
    public function testGet_Timeout_Connection_Bounded(): void
    {
        $client = $this->factory->get(null);
        $start = microtime(true);
        try {
            // Unroutable address: the connection attempts stall until the connect timeout is reached.
            $client->sendRequest(new Request('GET', 'http://10.255.255.1/'));
            self::fail('Expected a ConnectException.');
        } catch (ConnectException) {
            // 3 attempts x 1 s connect timeout (test config) + retry delays, must stay far below
            // the time a request without a timeout would take.
            self::assertLessThan(8.0, microtime(true) - $start);
        }
    }

    private function createFactory(?MockHandler $mock = null): HttpClientFactory
    {
        $h = new Helper();
        $em = $h->getEm();
        $storage = new DatabaseStorage(
            new RepositoryFactory($em),
            new ObjectManager($em, $this->logger),
        );

        return new HttpClientFactory(
            new Config([
                'guzzle' => [
                    'cache' => [
                        'dir' => __DIR__,
                    ],
                    'user_agent' => 'Test',
                    'connect_timeout' => 1,
                    'timeout' => 2,
                ],
            ]),
            new EsiErrorLimit($storage),
            new EsiWarnings($this->logger),
            new EsiRateLimits($this->logger, $storage),
            new EsiThrottled($storage),
            $this->logger,
            0,
            $mock,
        );
    }
}
