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
}
