<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

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
    private HttpClientFactory $factory;

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
        $storage = new DatabaseStorage(
            new RepositoryFactory($em),
            new ObjectManager($em, $logger),
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
            new EsiWarnings($logger),
            new EsiRateLimits($logger, $storage),
            new EsiThrottled($storage),
            $logger,
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
}
