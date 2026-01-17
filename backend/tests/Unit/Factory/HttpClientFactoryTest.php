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
use Neucore\Storage\SystemVariableStorage;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class HttpClientFactoryTest extends TestCase
{
    private HttpClientFactory $factory;

    private Connection $dbalConnection;

    protected function setUp(): void
    {
        $h = new Helper();
        $h->emptyDb();

        $logger = new Logger('test');
        $em = $h->getEm();
        $this->dbalConnection = $em->getConnection();
        $storage = new SystemVariableStorage(
            new RepositoryFactory($em),
            new ObjectManager($em, $logger),
        );
        $this->factory = new HttpClientFactory(
            new Config([
                'guzzle' => [
                    'cache' => ['table' => 'cache_http'],
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
    public function testGet(): void
    {
        $this->factory->get('cache-key1');

        $result = $this->dbalConnection->executeQuery('SELECT item_id FROM cache_http');
        self::assertSame([['item_id' => 'cache-key1:init']], $result->fetchAllAssociative());
    }

    /**
     * @throws Exception
     */
    public function testGetGuzzleClient(): void
    {
        $this->factory->getGuzzleClient(null);

        $result = $this->dbalConnection->executeQuery('SELECT item_id FROM cache_http');
        self::assertSame([], $result->fetchAllAssociative());
    }

    public function testCreateRequest(): void
    {
        $actual = $this->factory->createRequest(
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
