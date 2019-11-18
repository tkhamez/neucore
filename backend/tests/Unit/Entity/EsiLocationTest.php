<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\EsiLocation;
use PHPUnit\Framework\TestCase;

class EsiLocationTest extends TestCase
{
    public function testJsonSerialize()
    {
        $location = new EsiLocation();
        $location->setId(123);
        $location->setName('The name');
        $location->setCategory(EsiLocation::CATEGORY_STRUCTURE);

        $this->assertSame([
            'id' => 123,
            'name' => 'The name',
            'category' => EsiLocation::CATEGORY_STRUCTURE,
        ], json_decode((string) json_encode($location), true));
    }

    public function testGetSetId()
    {
        $location = new EsiLocation();
        $location->setId(123);
        $this->assertSame(123, $location->getId());
    }

    public function testSetGetName()
    {
        $location = new EsiLocation();
        $location->setName('The name');
        $this->assertSame('The name', $location->getName());
    }

    public function testSetGetCategory()
    {
        $location = new EsiLocation();

        $location->setCategory('invalid');
        $this->assertSame('', $location->getCategory());

        $location->setCategory(EsiLocation::CATEGORY_STRUCTURE);
        $this->assertSame(EsiLocation::CATEGORY_STRUCTURE, $location->getCategory());
    }

    public function testGetSetOwnerId()
    {
        $location = new EsiLocation();
        $location->setOwnerId(123);
        $this->assertSame(123, $location->getOwnerId());
    }

    public function testGetSetSystemId()
    {
        $location = new EsiLocation();
        $location->setSystemId(123);
        $this->assertSame(123, $location->getSystemId());
    }

    public function testSetGetLastUpdate()
    {
        $dt1 = new \DateTime('2019-11-18 19:34:14');

        $location = new EsiLocation();
        $location->setLastUpdate($dt1);
        $dt2 = $location->getLastUpdate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2019-11-18T19:34:14+00:00', $dt2->format(\DateTime::ATOM));
    }
}
