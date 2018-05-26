<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\App;
use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\Player;

class GroupTest extends \PHPUnit\Framework\TestCase
{
    public function testJsonSerialize()
    {
        $group = new Group();
        $group->setName('g.name');

        $this->assertSame(
            ['id' => null, 'name' => 'g.name', 'visibility' => Group::VISIBILITY_PRIVATE],
            json_decode(json_encode($group), true)
        );
    }

    public function testGetId()
    {
        $this->assertNull((new Group)->getId());
    }

    public function testSetGetName()
    {
        $group = new Group();
        $group->setName('nam');
        $this->assertSame('nam', $group->getName());
    }

    public function testSetVisibilityException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter must be one of ');

        $group = new Group();
        $group->setVisibility('invalid');
    }

    public function testSetGetVisibility()
    {
        $group = new Group();
        $this->assertsame(Group::VISIBILITY_PRIVATE, $group->getVisibility());
        $group->setVisibility(Group::VISIBILITY_PUBLIC);
        $this->assertsame(Group::VISIBILITY_PUBLIC, $group->getVisibility());
    }

    public function testAddGetRemoveApplicant()
    {
        $group = new Group();
        $a1 = new Player();
        $a2 = new Player();

        $this->assertSame([], $group->getApplicants());

        $group->addApplicant($a1);
        $group->addApplicant($a2);
        $this->assertSame([$a1, $a2], $group->getApplicants());

        $group->removeApplicant($a2);
        $this->assertSame([$a1], $group->getApplicants());
    }

    public function testAddGetRemovePlayer()
    {
        $group = new Group();
        $p1 = new Player();
        $p2 = new Player();

        $this->assertSame([], $group->getPlayers());

        $group->addPlayer($p1);
        $group->addPlayer($p2);
        $this->assertSame([$p1, $p2], $group->getPlayers());

        $group->removePlayer($p2);
        $group->removePlayer($p1);
        $this->assertSame([], $group->getPlayers());
    }

    public function testAddGetRemoveManager()
    {
        $group = new Group();
        $p1 = new Player();
        $p2 = new Player();

        $this->assertSame([], $group->getManagers());

        $group->addManager($p1);
        $group->addManager($p2);
        $this->assertSame([$p1, $p2], $group->getManagers());

        $group->removeManager($p2);
        $this->assertSame([$p1], $group->getManagers());
    }

    public function testAddGetRemoveApp()
    {
        $group = new Group();
        $a1 = new App();
        $a2 = new App();

        $this->assertSame([], $group->getApps());

        $group->addApp($a1);
        $group->addApp($a2);
        $this->assertSame([$a1, $a2], $group->getApps());

        $group->removeApp($a2);
        $group->removeApp($a1);
        $this->assertSame([], $group->getApps());
    }

    public function testAddGetRemoveCorporation()
    {
        $group = new Group();
        $c1 = new Corporation();
        $c2 = new Corporation();

        $this->assertSame([], $group->getCorporations());

        $group->addCorporation($c1);
        $group->addCorporation($c2);
        $this->assertSame([$c1, $c2], $group->getCorporations());

        $group->removeCorporation($c2);
        $this->assertSame([$c1], $group->getCorporations());
    }

    public function testAddGetRemoveAlliance()
    {
        $group = new Group();
        $a1 = new Alliance();
        $a2 = new Alliance();

        $this->assertSame([], $group->getAlliances());

        $group->addAlliance($a1);
        $group->addAlliance($a2);
        $this->assertSame([$a1, $a2], $group->getAlliances());

        $group->removeAlliance($a2);
        $this->assertSame([$a1], $group->getAlliances());
    }
}
