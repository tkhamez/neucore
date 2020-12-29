<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Service;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    public function testJsonSerialize()
    {
        $service = new Service();
        $service->setName('s1');

        $this->assertSame(
            ['id' => 0, 'name' => 's1'],
            json_decode((string) json_encode($service), true)
        );
        $this->assertSame(
            ['id' => 0, 'name' => 's1', 'configuration' => null],
            $service->jsonSerialize(false)
        );
    }

    public function testGetId()
    {
        $this->assertSame(0, (new Service())->getId());
    }

    public function testSetGetName()
    {
        $service = new Service();
        $this->assertSame('', $service->getName());
        $this->assertSame('name',  $service->setName('name')->getName());
    }

    public function testSetGetConfiguration()
    {
        $service = new Service();
        $this->assertNull($service->getConfiguration());
        $this->assertSame('{}', $service->setConfiguration('{}')->getConfiguration());
    }
}
