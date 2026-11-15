<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use Neucore\Exception\RuntimeException;
use Neucore\Storage\ApcuStorage;
use PHPUnit\Framework\TestCase;

class ApcuStorageTest extends TestCase
{
    private ApcuStorage $storage;

    protected function setup(): void
    {
        if (!function_exists('apcu_store')) {
            $this->markTestSkipped('APCu is not installed.');
        }
        if (ini_get('apc.enable_cli') === '0') {
            $this->markTestSkipped('APCu for CLI is not enabled.');
        }

        /** @noinspection PhpComposerExtensionStubsInspection */
        apcu_clear_cache();
        $this->storage = new ApcuStorage();
    }

    public function testSetException1(): void
    {
        $this->expectException(RuntimeException::class);
        $this->storage->set('key' . str_repeat('1', 110), 'value');
    }

    public function testSet(): void
    {
        $this->assertTrue($this->storage->set('key', 'value'));

        /** @noinspection PhpComposerExtensionStubsInspection */
        $this->assertSame('value', apcu_fetch(ApcuStorage::PREFIX . 'key'));
    }

    public function testGet(): void
    {
        $this->assertNull($this->storage->get('key'));

        $this->storage->set('key', 'value');
        $this->assertSame('value', $this->storage->get('key'));
    }

    public function testConsumeException1(): void
    {
        $this->expectException(RuntimeException::class);
        $this->storage->consume('key' . str_repeat('1', 110), 10, 60);
    }

    public function testConsume(): void
    {
        $now = microtime(true);
        $counter = $this->storage->consume('key', 10, 60);
        $this->assertSame(9, $counter->remaining);
        $this->assertGreaterThanOrEqual($now, $counter->created);
        $this->assertLessThanOrEqual($now + 1, $counter->created);

        $counter = $this->storage->consume('key', 10, 60);
        $this->assertSame(8, $counter->remaining);
    }

    public function testConsume_WindowExpired(): void
    {
        $state = new \stdClass();
        $state->remaining = 0;
        $state->created = microtime(true) - 120;
        $this->storage->set('key', (string) \json_encode($state));

        $counter = $this->storage->consume('key', 10, 60);
        $this->assertSame(9, $counter->remaining);
    }

    public function testConsume_InvalidValue(): void
    {
        $this->storage->set('key', 'invalid json');

        $counter = $this->storage->consume('key', 10, 60);
        $this->assertSame(9, $counter->remaining);
    }
}
