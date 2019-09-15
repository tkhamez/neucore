<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\App;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
    public function testJsonSerialize()
    {
        $group = new Group();
        $group->setName('g.name');

        $this->assertSame(
            ['id' => null, 'name' => 'g.name', 'visibility' => Group::VISIBILITY_PRIVATE],
            json_decode((string) json_encode($group), true)
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

    public function testAddGetRemoveApplication()
    {
        $group = new Group();
        $a1 = new GroupApplication();
        $a2 = new GroupApplication();

        $this->assertSame([], $group->getApplications());

        $group->addApplication($a1);
        $group->addApplication($a2);
        $this->assertSame([$a1, $a2], $group->getApplications());

        $group->removeApplication($a2);
        $this->assertSame([$a1], $group->getApplications());
    }

    public function testAddGetRemovePlayer()
    {
        $this->assertSame([], (new Group())->getPlayers());
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

    public function testAddGetRemoveRequiredGroups()
    {
        $group = new Group();
        $required1 = new Group();
        $required2 = new Group();

        $this->assertSame([], $group->getRequiredGroups());

        $group->addRequiredGroup($required1);
        $group->addRequiredGroup($required2);
        $this->assertSame([$required1, $required2], $group->getRequiredGroups());

        $group->removeRequiredGroup($required2);
        $this->assertSame([$required1], $group->getRequiredGroups());
    }

    public function testAddGetRemoveRequiredBy()
    {
        $group = new Group();
        $dependent1 = new Group();
        $dependent2 = new Group();

        $this->assertSame([], $group->getRequiredBy());

        $group->addRequiredBy($dependent1);
        $group->addRequiredBy($dependent2);
        $this->assertSame([$dependent1, $dependent2], $group->getRequiredBy());

        $group->removeRequiredBy($dependent2);
        $this->assertSame([$dependent1], $group->getRequiredBy());
    }
}
