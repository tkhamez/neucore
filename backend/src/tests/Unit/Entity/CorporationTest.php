<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Group;
use PHPUnit\Framework\TestCase;

class CorporationTest extends TestCase
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
            'ticker' => 'ABC',
            'alliance' => null
        ], json_decode((string) json_encode($corp), true));
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
        $corp = new Corporation();
        $corp->setTicker('ABC');
        $this->assertSame('ABC', $corp->getTicker());
    }

    /**
     * @throws \Exception
     */
    public function testSetGetLastUpdate()
    {
        $dt1 = new \DateTime('2018-04-26 18:59:36');

        $corp = new Alliance();
        $corp->setLastUpdate($dt1);
        $dt2 = $corp->getLastUpdate();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2018-04-26T18:59:36+00:00', $dt2->format(\DateTime::ATOM));
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

    /**
     * @throws \ReflectionException
     */
    public function testHasGroup()
    {
        $group1 = new Group();
        $group2 = new Group();

        $rp = new \ReflectionProperty(Group::class, 'id');
        $rp->setAccessible(true);
        $rp->setValue($group1, 1);
        $rp->setValue($group2, 2);

        $corp = new Corporation();
        $corp->addGroup($group1);

        $this->assertTrue($corp->hasGroup($group1->getId()));
        $this->assertFalse($corp->hasGroup($group2->getId()));
    }

    public function testAddGetRemoveGroupTracking()
    {
        $corp = new Corporation();
        $g1 = new Group();
        $g2 = new Group();

        $this->assertSame([], $corp->getGroupsTracking());

        $corp->addGroupTracking($g1);
        $corp->addGroupTracking($g2);
        $this->assertSame([$g1, $g2], $corp->getGroupsTracking());

        $corp->removeGroupTracking($g2);
        $this->assertSame([$g1], $corp->getGroupsTracking());
    }

    public function testHasGroupTracking()
    {
        $group1 = new Group();
        $group2 = new Group();

        $rp = new \ReflectionProperty(Group::class, 'id');
        $rp->setAccessible(true);
        $rp->setValue($group1, 1);
        $rp->setValue($group2, 2);

        $corp = new Corporation();
        $corp->addGroupTracking($group1);

        $this->assertTrue($corp->hasGroupTracking($group1->getId()));
        $this->assertFalse($corp->hasGroupTracking($group2->getId()));
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

    public function testAddGetRemoveMember()
    {
        $corp = new Corporation();
        $cm1 = new CorporationMember();
        $cm2 = new CorporationMember();

        $this->assertSame([], $corp->getMembers());

        $corp->addMember($cm1);
        $corp->addMember($cm2);
        $this->assertSame([$cm1, $cm2], $corp->getMembers());

        $this->assertTrue($corp->removeMember($cm2));
        $this->assertSame([$cm1], $corp->getMembers());
    }
}
