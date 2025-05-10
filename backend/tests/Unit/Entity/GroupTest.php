<?php

/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\App;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Plugin\Data\CoreGroup;
use PHPUnit\Framework\TestCase;

class GroupTest extends TestCase
{
    public function testJsonSerialize()
    {
        $group = new Group();
        $group->setName('g.name');

        self::assertSame(
            ['id' => null, 'name' => 'g.name', 'description' => null,
                'visibility' => Group::VISIBILITY_PRIVATE, 'autoAccept' => false,
                'isDefault' => false, 'isAutoManaged' => null],
            json_decode((string) json_encode($group), true),
        );
    }

    public function testGetId()
    {
        self::assertSame(0, (new Group())->getId());
    }

    public function testSetGetName()
    {
        $group = new Group();
        $group->setName('nam');
        self::assertSame('nam', $group->getName());
    }

    public function testSetGetDescription()
    {
        $group = new Group();
        $group->setDescription("Hell\no");
        self::assertSame("Hell\no", $group->getDescription());
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
        self::assertsame(Group::VISIBILITY_PRIVATE, $group->getVisibility());
        $group->setVisibility(Group::VISIBILITY_PUBLIC);
        self::assertsame(Group::VISIBILITY_PUBLIC, $group->getVisibility());
    }

    public function testSetGetAutoAccept()
    {
        $group = new Group();
        self::assertFalse($group->getAutoAccept());
        $group->setAutoAccept(true);
        self::assertTrue($group->getAutoAccept());
    }


    public function testSetGetIsDefault()
    {
        $group = new Group();
        self::assertFalse($group->getIsDefault());
        $group->setIsDefault(true);
        self::assertTrue($group->getIsDefault());
    }

    public function testAddGetRemoveApplication()
    {
        $group = new Group();
        $a1 = new GroupApplication();
        $a2 = new GroupApplication();

        self::assertSame([], $group->getApplications());

        $group->addApplication($a1);
        $group->addApplication($a2);
        self::assertSame([$a1, $a2], $group->getApplications());

        $group->removeApplication($a2);
        self::assertSame([$a1], $group->getApplications());
    }

    public function testAddGetRemovePlayer()
    {
        self::assertSame([], (new Group())->getPlayers());
    }

    public function testAddGetRemoveManager()
    {
        $group = new Group();
        $p1 = new Player();
        $p2 = new Player();

        self::assertSame([], $group->getManagers());

        $group->addManager($p1);
        $group->addManager($p2);
        self::assertSame([$p1, $p2], $group->getManagers());

        $group->removeManager($p2);
        self::assertSame([$p1], $group->getManagers());
    }

    public function testAddGetRemoveApp()
    {
        $group = new Group();
        $a1 = new App();
        $a2 = new App();

        self::assertSame([], $group->getApps());

        $group->addApp($a1);
        $group->addApp($a2);
        self::assertSame([$a1, $a2], $group->getApps());

        $group->removeApp($a2);
        $group->removeApp($a1);
        self::assertSame([], $group->getApps());
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

        self::assertSame([], $group->getRequiredGroups());

        $group->addRequiredGroup($required1);
        $group->addRequiredGroup($required2);
        self::assertSame([$required1, $required2], $group->getRequiredGroups());

        $group->removeRequiredGroup($required1);
        self::assertSame([$required2], $group->getRequiredGroups());
    }

    public function testAddGetRemoveForbiddenGroups()
    {
        $group = new Group();
        $forbidden1 = new Group();
        $forbidden2 = new Group();

        self::assertSame([], $group->getForbiddenGroups());

        $group->addForbiddenGroup($forbidden1);
        $group->addForbiddenGroup($forbidden2);
        self::assertSame([$forbidden1, $forbidden2], $group->getForbiddenGroups());

        $group->removeForbiddenGroup($forbidden1);
        self::assertSame([$forbidden2], $group->getForbiddenGroups());
    }

    public function testToCoreGroup()
    {
        $group = (new Group())->setName('G1');

        self::assertInstanceOf(CoreGroup::class, $group->toCoreGroup());
        self::assertSame(0, $group->toCoreGroup()->identifier);
        self::assertSame('G1', $group->toCoreGroup()->name);
    }

    public function testSetIsAutoManaged()
    {
        $group1 = new Group();
        self::assertFalse($group1->setIsAutoManaged());

        $group2 = new Group();
        $group2->addAlliance(new Alliance());
        self::assertTrue($group2->setIsAutoManaged());

        $group3 = new Group();
        $group3->addCorporation(new Corporation());
        self::assertTrue($group3->setIsAutoManaged());
    }
}
