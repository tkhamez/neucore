<?php
/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Tests\Unit\Storage;

use Neucore\Application;
use Neucore\Exception\RuntimeException;
use Neucore\Storage\MemcachedStorage;
use PHPUnit\Framework\TestCase;

class MemcachedStorageTest extends TestCase
{
    private static \Memcached $memcached;

    private MemcachedStorage $storage;

    private string $key = 'test_key';

    public static function setUpBeforeClass(): void
    {
        /** @noinspection PhpComposerExtensionStubsInspection */
        if (!class_exists(\Memcached::class)) {
            self::markTestSkipped('Memcached not available.');
        }

        $config = (new Application())->loadSettings(true);
        $server = $config['memcached']['server'];
        if (!str_contains($server, ':')) {
            self::markTestSkipped('Memcached not available.');
        }
        [$host, $port] = explode(':', $server);

        self::$memcached = new \Memcached();
        self::$memcached->addServer($host, (int) $port);
    }

    protected function setup(): void
    {
        $this->storage = new MemcachedStorage(self::$memcached);
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
    }

    public function testGet(): void
    {
        $this->assertNull($this->storage->get($this->key));

        self::$memcached->set(MemcachedStorage::PREFIX . $this->key, 'val');
        $this->assertSame('val', $this->storage->get($this->key));
    }
}
