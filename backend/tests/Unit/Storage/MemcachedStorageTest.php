<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Tests\Unit\Storage;

use Neucore\Application;
use Neucore\Exception\RuntimeException;
use Neucore\Storage\MemcachedStorage;
use PHPUnit\Framework\TestCase;
use Tests\Logger;

class MemcachedStorageTest extends TestCase
{
    private static \Memcached $memcached;

    private MemcachedStorage $storage;

    private Logger $logger;

    private string $key = 'test_key';

    public static function setUpBeforeClass(): void
    {
        $config = (new Application())->loadSettings(true);
        $server = $config['memcached']['server'];

        if (!extension_loaded('memcached') || !str_contains($server, ':')) {
            self::markTestSkipped('Memcached not available or missing server configuration.');
        }

        [$host, $port] = explode(':', $server);
        self::$memcached = new \Memcached();
        self::$memcached->addServer($host, (int) $port);
    }

    protected function setup(): void
    {
        $this->logger = new Logger();
        $this->storage = new MemcachedStorage(self::$memcached, $this->logger);
    }

    protected function tearDown(): void
    {
        self::$memcached->delete(MemcachedStorage::PREFIX . $this->key);
    }

    public function testSetException1(): void
    {
        $this->expectException(RuntimeException::class);
        $this->storage->set('key' . str_repeat('1', 110), 'value');
    }

    public function testSet(): void
    {
        $this->assertTrue($this->storage->set($this->key, 'value'));

        $this->assertSame(
            'value',
            self::$memcached->get(MemcachedStorage::PREFIX . $this->key),
        );
        $this->assertCount(0, $this->logger->getMessages());
    }

    public function testSetStorageFails(): void
    {
        $logger = new Logger();
        $memcached = $this->createMock(\Memcached::class);
        $memcached->method('set')->willReturn(false);
        $storage = new MemcachedStorage($memcached, $logger);

        $this->assertFalse($storage->set('key', 'value'));

        $logs = $logger->getMessages();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString("Failed to set 'key'", $logs[0]);
    }

    public function testGet(): void
    {
        $this->assertNull($this->storage->get($this->key));
        $this->assertCount(0, $this->logger->getMessages());

        self::$memcached->set(MemcachedStorage::PREFIX . $this->key, 'val');
        $this->assertSame('val', $this->storage->get($this->key));
    }

    public function testGetStorageFails(): void
    {
        $logger = new Logger();
        $memcached = $this->createMock(\Memcached::class);
        $memcached->method('get')->willReturn(false);
        $memcached->method('getResultCode')->willReturn(\Memcached::RES_CONNECTION_FAILURE);
        $storage = new MemcachedStorage($memcached, $logger);

        $this->assertNull($storage->get('key'));

        $logs = $logger->getMessages();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString("Failed to get 'key'", $logs[0]);
    }

    public function testGetKeyNotFound_NoErrorLog(): void
    {
        $logger = new Logger();
        $memcached = $this->createMock(\Memcached::class);
        $memcached->method('get')->willReturn(false);
        $memcached->method('getResultCode')->willReturn(\Memcached::RES_NOTFOUND);
        $storage = new MemcachedStorage($memcached, $logger);

        $this->assertNull($storage->get('key'));
        $this->assertCount(0, $logger->getMessages());
    }

    public function testConsumeException1(): void
    {
        $this->expectException(RuntimeException::class);
        $this->storage->consume('key' . str_repeat('1', 110), 10, 60);
    }

    public function testConsume(): void
    {
        $now = microtime(true);
        $counter = $this->storage->consume($this->key, 10, 60);
        $this->assertSame(9, $counter->remaining);
        $this->assertGreaterThanOrEqual($now, $counter->created);
        $this->assertLessThanOrEqual($now + 1, $counter->created);

        $counter = $this->storage->consume($this->key, 10, 60);
        $this->assertSame(8, $counter->remaining);
    }

    public function testConsume_WindowExpired(): void
    {
        $state = new \stdClass();
        $state->remaining = 0;
        $state->created = microtime(true) - 120;
        $this->storage->set($this->key, (string) \json_encode($state));

        $counter = $this->storage->consume($this->key, 10, 60);
        $this->assertSame(9, $counter->remaining);
    }

    public function testConsume_InvalidValue(): void
    {
        $this->storage->set($this->key, 'invalid json');

        $counter = $this->storage->consume($this->key, 10, 60);
        $this->assertSame(9, $counter->remaining);
    }

    public function testConsume_UnexpectedGetResult(): void
    {
        $memcached = $this->createMock(\Memcached::class);
        $memcached->method('add')->willReturn(false);
        $memcached->method('get')->willReturn(null);
        $storage = new MemcachedStorage($memcached, new Logger());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to consume the rate limit counter (1).');

        $storage->consume('key', 10, 60);
    }
}
