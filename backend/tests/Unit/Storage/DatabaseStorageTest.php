<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use Neucore\Entity\SystemVariable;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\ObjectManager;
use Neucore\Storage\DatabaseStorage;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class DatabaseStorageTest extends TestCase
{
    private DatabaseStorage $storage;

    private RepositoryFactory $repoFactory;

    protected function setup(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();
        $this->repoFactory = new RepositoryFactory($om);
        $this->storage = new DatabaseStorage($this->repoFactory, new ObjectManager($om, new Logger()));
    }

    public function testSetException1(): void
    {
        $this->expectException(RuntimeException::class);
        $this->storage->set('key' . str_repeat('1', 110), 'value');
    }

    public function testSet(): void
    {
        $this->assertTrue($this->storage->set('key', 'value'));

        $var = $this->repoFactory->getSystemVariableRepository()->find(DatabaseStorage::PREFIX . 'key');
        $this->assertSame('value', $var?->getValue());
        $this->assertSame(SystemVariable::SCOPE_BACKEND, $var->getScope());
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
