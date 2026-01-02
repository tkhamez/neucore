<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use Neucore\Entity\SystemVariable;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\ObjectManager;
use Neucore\Storage\SystemVariableStorage;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class SystemVariableStorageTest extends TestCase
{
    private SystemVariableStorage $storage;

    private RepositoryFactory $repoFactory;

    protected function setup(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();
        $this->repoFactory = new RepositoryFactory($om);
        $this->storage = new SystemVariableStorage($this->repoFactory, new ObjectManager($om, new Logger()));
    }

    public function testSetException1(): void
    {
        $this->expectException(RuntimeException::class);
        $this->storage->set('key' . str_repeat('1', 110), 'value');
    }

    public function testSet(): void
    {
        $this->assertTrue($this->storage->set('key', 'value'));

        $var = $this->repoFactory->getSystemVariableRepository()->find(SystemVariableStorage::PREFIX . 'key');
        $this->assertSame('value', $var?->getValue());
        $this->assertSame(SystemVariable::SCOPE_BACKEND, $var->getScope());
    }

    public function testGet(): void
    {
        $this->assertNull($this->storage->get('key'));

        $this->storage->set('key', 'value');
        $this->assertSame('value', $this->storage->get('key'));
    }
}
