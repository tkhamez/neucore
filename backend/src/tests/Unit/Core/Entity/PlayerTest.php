<?php

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\App;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\Role;

class PlayerTest extends \PHPUnit\Framework\TestCase
{
    public function testJsonSerialize()
    {
        $a1 = (new App())->setName('app-one');
        $g1 = (new Group())->setName('gname');
        $g2 = (new Group())->setName('group2');
        $play = new Player();
        $play->setName('test user');
        $play->addGroup($g1);
        $play->addGroup($g2);
        $play->addRole((new Role())->setName('rname'));
        $play->addRole((new Role())->setName('role2'));
        $c1 = new Character();
        $c2 = new Character();
        $c1->setId(123);
        $c2->setId(234);
        $c1->setMain(true);
        $c2->setMain(false);
        $c1->setName('eve one');
        $c2->setName('eve two');
        $play->addCharacter($c1);
        $play->addCharacter($c2);
        $play->addManagerGroup($g1);
        $play->addManagerApp($a1);

        $this->assertSame([
            'name'       => 'test user',
            'roles'      => ['rname', 'role2'],
            'groups'     => ['gname', 'group2'],
            'characters' => [
                ['id' => 123, 'name' => 'eve one', 'main' => true],
                ['id' => 234, 'name' => 'eve two', 'main' => false],
            ],
            'managerGroups' => ['gname'],
            'managerApps'   => [['id' => null, 'name' => 'app-one']],
        ], json_decode(json_encode($play), true));
    }

    public function testGetId()
    {
        $this->assertNull((new Player())->getId());
    }

    public function testSetGetName()
    {
        $play = new Player();
        $play->setName('nam');
        $this->assertSame('nam', $play->getName());
    }

    public function testAddGetRemoveRole()
    {
        $play = new Player();
        $r1 = new Role();
        $r2 = new Role();

        $this->assertSame([], $play->getRoles()->toArray());

        $play->addRole($r1);
        $play->addRole($r2);
        $this->assertSame([$r1, $r2], $play->getRoles()->toArray());

        $play->removeRole($r2);
        $this->assertSame([$r1], $play->getRoles()->toArray());
    }

    public function testAddGetRemoveGroup()
    {
        $play = new Player();
        $g1 = new Group();
        $g2 = new Group();

        $this->assertSame([], $play->getGroups()->toArray());

        $play->addGroup($g1);
        $play->addGroup($g2);
        $this->assertSame([$g1, $g2], $play->getGroups()->toArray());

        $play->removeGroup($g2);
        $this->assertSame([$g1], $play->getGroups()->toArray());
    }

    public function testAddGetRemoveCharacter()
    {
        $play = new Player();
        $c1 = new Character();
        $c2 = new Character();

        $this->assertSame([], $play->getCharacters()->toArray());

        $play->addCharacter($c1);
        $play->addCharacter($c2);
        $this->assertSame([$c1, $c2], $play->getCharacters()->toArray());

        $play->removeCharacter($c2);
        $this->assertSame([$c1], $play->getCharacters()->toArray());
    }

    public function testAddGetRemoveManagerGroups()
    {
        $play = new Player();
        $g1 = new Group();
        $g2 = new Group();

        $this->assertSame([], $play->getManagerGroups()->toArray());

        $play->addManagerGroup($g1);
        $play->addManagerGroup($g2);
        $this->assertSame([$g1, $g2], $play->getManagerGroups()->toArray());

        $play->removeManagerGroup($g2);
        $this->assertSame([$g1], $play->getManagerGroups()->toArray());
    }

    public function testAddGetRemoveManagerApps()
    {
        $play = new Player();
        $a1 = new App();
        $a2 = new App();

        $this->assertSame([], $play->getManagerApps()->toArray());

        $play->addManagerApp($a1);
        $play->addManagerApp($a2);
        $this->assertSame([$a1, $a2], $play->getManagerApps()->toArray());

        $play->removeManagerApp($a2);
        $this->assertSame([$a1], $play->getManagerApps()->toArray());
    }
}
