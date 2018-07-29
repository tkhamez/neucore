<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Group;

class AllianceTest extends \PHPUnit\Framework\TestCase
{
    public function testJsonSerialize()
    {
        $alli = new Alliance();
        $alli->setId(123);
        $alli->setName('test alli');
        $alli->setTicker('ABC');

        $this->assertSame([
            'id' => 123,
            'name' => 'test alli',
            'ticker' => 'ABC'
        ], json_decode(json_encode($alli), true));
    }

    public function testSetGetId()
    {
        $alli = new Alliance();
        $alli->setId(123);
        $this->assertSame(123, $alli->getId());
    }

    public function testSetGetName()
    {
        $alli = new Alliance();
        $alli->setName('nam');
        $this->assertSame('nam', $alli->getName());
    }

    public function testSetGetTicker()
    {
        $alli = new Alliance();
        $alli->setTicker('ABC');
        $this->assertSame('ABC', $alli->getTicker());
    }

    public function testSetGetLastUpdate()
    {
        $dt1 = new \DateTime('2018-04-26 18:59:36');

        $alli = new Alliance();
        $alli->setLastUpdate($dt1);
        $dt2 = $alli->getLastUpdate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-04-26T18:59:36+00:00', $dt2->format(\DateTime::ATOM));
    }

    public function testAddGetRemoveCorporation()
    {
        $alli = new Alliance();
        $c1 = new Corporation();
        $c2 = new Corporation();

        $this->assertSame([], $alli->getCorporations());

        $alli->addCorporation($c1);
        $alli->addCorporation($c2);
        $this->assertSame([$c1, $c2], $alli->getCorporations());

        $alli->removeCorporation($c2);
        $this->assertSame([$c1], $alli->getCorporations());
    }

    public function testAddGetRemoveGroup()
    {
        $alli = new Alliance();
        $g1 = new Group();
        $g2 = new Group();

        $this->assertSame([], $alli->getGroups());

        $alli->addGroup($g1);
        $alli->addGroup($g2);
        $this->assertSame([$g1, $g2], $alli->getGroups());

        $alli->removeGroup($g2);
        $this->assertSame([$g1], $alli->getGroups());
    }

    public function testHasGroup()
    {
        $group1 = new Group();
        $group2 = new Group();

        $rp = new \ReflectionProperty(Group::class, 'id');
        $rp->setAccessible(true);
        $rp->setValue($group1, 1);
        $rp->setValue($group2, 2);

        $alli = new Alliance();
        $alli->addGroup($group1);

        $this->assertTrue($alli->hasGroup($group1->getId()));
        $this->assertFalse($alli->hasGroup($group2->getId()));
    }
}
