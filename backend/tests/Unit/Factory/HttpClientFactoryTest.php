<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use GuzzleHttp\ClientInterface;
use Monolog\Logger;
use Neucore\Factory\HttpClientFactory;
use Neucore\Middleware\Guzzle\Esi429Response;
use Neucore\Middleware\Guzzle\EsiHeaders;
use Neucore\Service\Config;
use Neucore\Storage\ApcuStorage;
use PHPUnit\Framework\TestCase;

class HttpClientFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        if (is_dir(__DIR__ . '/cache-key')) {
            rmdir(__DIR__ . '/cache-key');
        }
    }

    public function testGet()
    {
        $logger = new Logger('test');
        $factory = new HttpClientFactory(
            new Config(['guzzle' => ['cache' => ['dir' => __DIR__], 'user_agent' => 'Test']]),
            new EsiHeaders($logger, new ApcuStorage()),
            new Esi429Response($logger, new ApcuStorage()),
            $logger
        );

        $actual = $factory->get('cache-key');

        $this->assertInstanceOf(ClientInterface::class, $actual);
        $this->assertTrue(is_dir(__DIR__ . '/cache-key'));
    }
}
