<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Monolog\Logger;
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
use Tests\Helper;

class HttpClientFactoryTest extends TestCase
{
    private HttpClientFactory $factory1;

    private HttpClientFactory $factory2;

    private Connection $dbalConnection;

    protected function setUp(): void
    {
        if (is_dir(__DIR__ . '/cache-key/@')) {
            rmdir(__DIR__ . '/cache-key/@');
            rmdir(__DIR__ . '/cache-key');
        }

        $h = new Helper();
        $h->emptyDb();

        $logger = new Logger('test');
        $em = $h->getEm();
        $this->dbalConnection = $em->getConnection();
        $storage = new DatabaseStorage(
            new RepositoryFactory($em),
            new ObjectManager($em, $logger),
        );

        $this->factory1 = new HttpClientFactory(
            new Config([
                'guzzle' => [
                    'cache' => [
                        'storage' => HttpClientFactory::CACHE_STORAGE_DATABASE,
                        'table' => 'cache_http',
                        #'dir' => __DIR__,
                    ],
                    'user_agent' => 'Test',
                ],
            ]),
            new EsiErrorLimit($storage),
            new EsiWarnings($logger),
            new EsiRateLimits($logger, $storage),
            new EsiThrottled($storage),
            $logger,
            $this->dbalConnection
        );

        $this->factory2 = new HttpClientFactory(
            new Config([
                'guzzle' => [
                    'cache' => [
                        'storage' => HttpClientFactory::CACHE_STORAGE_FILESYSTEM,
                        #'table' => 'cache_http',
                        'dir' => __DIR__,
                    ],
                    'user_agent' => 'Test',
                ],
            ]),
            new EsiErrorLimit($storage),
            new EsiWarnings($logger),
            new EsiRateLimits($logger, $storage),
            new EsiThrottled($storage),
            $logger,
            $this->dbalConnection
        );
    }

    /**
     * @throws Exception
     */
    public function testGet_DatabaseCache(): void
    {
        $this->factory1->get('cache-key1');

        $result = $this->dbalConnection->executeQuery('SELECT item_id FROM cache_http');
        self::assertSame([['item_id' => 'cache-key1:init']], $result->fetchAllAssociative());
    }

    public function testGet_FilesystemCache(): void
    {
        $this->assertFalse(is_dir(__DIR__ . '/cache-key'));

        $this->factory2->get('cache-key');
        $this->assertTrue(is_dir(__DIR__ . '/cache-key'));
    }

    /**
     * @throws Exception
     */
    public function testGetGuzzleClient_DatabaseCache(): void
    {
        $this->factory1->getGuzzleClient(null);

        $result = $this->dbalConnection->executeQuery('SELECT item_id FROM cache_http');
        self::assertSame([], $result->fetchAllAssociative());
    }

    public function testGetGuzzleClient_FilesystemCache(): void
    {
        $this->factory2->getGuzzleClient(null);
        $this->assertFalse(is_dir(__DIR__ . '/cache-key'));
    }

    public function testCreateRequest(): void
    {
        $actual = $this->factory1->createRequest(
            'GET',
            'http://localhost',
            ['X-Header-Name' => 'value'],
            'body',
        );
        $this->assertSame('GET', $actual->getMethod());
        $this->assertSame('http://localhost', $actual->getUri()->__toString());
        $this->assertSame(['value'], $actual->getHeader('X-Header-Name'));
        $this->assertSame('body', $actual->getBody()->getContents());
    }
}
