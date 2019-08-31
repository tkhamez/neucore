<?php

namespace Tests\Unit\Entity;

use Neucore\Entity\EsiType;
use PHPUnit\Framework\TestCase;

class EsiTypeTest extends TestCase
{
    public function testJsonSerialize()
    {
        $type = new EsiType();
        $type->setId(123);
        $type->setName('The name');

        $this->assertSame([
            'id' => 123,
            'name' => 'The name',
        ], json_decode((string) json_encode($type), true));
    }

    public function testGetSetId()
    {
        $type = new EsiType();
        $type->setId(123);
        $this->assertSame(123, $type->getId());
    }

    public function testSetGetName()
    {
        $type = new EsiType();
        $type->setName('The name');
        $this->assertSame('The name', $type->getName());
    }
}
