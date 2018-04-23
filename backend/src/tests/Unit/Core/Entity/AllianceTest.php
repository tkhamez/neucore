<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Corporation;

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
}
