<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Group;

class CorporationTest extends \PHPUnit\Framework\TestCase
{
    public function testJsonSerialize()
    {
        $corp = new Corporation();
        $corp->setId(123);
        $corp->setName('test corp');
        $corp->setTicker('ABC');

        $this->assertSame([
            'id' => 123,
            'name' => 'test corp',
            'ticker' => 'ABC'
        ], json_decode(json_encode($corp), true));
    }

    public function testSetGetId()
    {
        $corp = new Corporation();
        $corp->setId(123);
        $this->assertSame(123, $corp->getId());
    }

    public function testSetGetName()
    {
        $corp = new Corporation();
        $corp->setName('corp');
        $this->assertSame('corp', $corp->getName());
    }

    public function testSetGetTicker()
    {
        $alli = new Corporation();
        $alli->setTicker('ABC');
        $this->assertSame('ABC', $alli->getTicker());
    }

    public function testSetGetAlliance()
    {
        $corp = new Corporation();
        $alli = new Alliance();
        $corp->setAlliance($alli);
        $this->assertSame($alli, $corp->getAlliance());
    }

    public function testAddGetRemoveGroup()
    {
        $corp = new Corporation();
        $g1 = new Group();
        $g2 = new Group();

        $this->assertSame([], $corp->getGroups());

        $corp->addGroup($g1);
        $corp->addGroup($g2);
        $this->assertSame([$g1, $g2], $corp->getGroups());

        $corp->removeGroup($g2);
        $this->assertSame([$g1], $corp->getGroups());
    }

    public function testAddGetRemoveCharacter()
    {
        $corp = new Corporation();
        $c1 = new Character();
        $c2 = new Character();

        $this->assertSame([], $corp->getCharacters());

        $corp->addCharacter($c1);
        $corp->addCharacter($c2);
        $this->assertSame([$c1, $c2], $corp->getCharacters());

        $corp->removeCharacter($c2);
        $this->assertSame([$c1], $corp->getCharacters());
    }
}
