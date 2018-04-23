<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\App;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
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
        $play->addApplication($g1);
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
        $c1->setCorporation((new Corporation())->setName('corp1')->setTicker('ABC')
            ->setAlliance((new Alliance())->setName('alli1')->setTicker('DEF')));
        $play->addCharacter($c1);
        $play->addCharacter($c2);
        $play->addManagerGroup($g1);
        $play->addManagerApp($a1);

        $this->assertSame([
            'id' => null,
            'name' => 'test user',
            'roles' => ['rname', 'role2'],
            'characters' => [
                ['id' => 123, 'name' => 'eve one', 'main' => true, 'corporation' => [
                    'id' => null, 'name' => 'corp1', 'ticker' => 'ABC', 'alliance' => [
                        'id' => null, 'name' => 'alli1', 'ticker' => 'DEF'
                    ]
                ]],
                ['id' => 234, 'name' => 'eve two', 'main' => false, 'corporation' => null],
            ],
            'applications' => [
                ['id' => null, 'name' => 'gname', 'public' => false]
            ],
            'groups' => [
                ['id' => null, 'name' => 'group2', 'public' => false]
            ],
            'managerGroups' => [['id' => null, 'name' => 'gname', 'public' => false]],
            'managerApps' => [['id' => null, 'name' => 'app-one']],
        ], json_decode(json_encode($play), true));
    }

    public function testGetId()
    {
        $this->assertNull((new Player())->getId());
    }

    public function testSetGetName()
    {
        $play= new Player();
        $play->setName('nam');
        $this->assertSame('nam', $play->getName());
    }

    public function testAddGetRemoveRole()
    {
        $player = new Player();
        $r1 = new Role();
        $r2 = new Role();
        $r1->setName('n1');
        $r2->setName('n2');

        $this->assertSame([], $player->getRoles());

        $player->addRole($r1);
        $player->addRole($r2);
        $this->assertSame([$r1, $r2], $player->getRoles());
        $this->assertSame(['n1', 'n2'], $player->getRoleNames());

        $player->removeRole($r2);
        $this->assertSame([$r1], $player->getRoles());
    }

    public function testHasRole()
    {
        $player = new Player();
        $role = new Role();
        $role->setName('role1');
        $player->addRole($role);

        $this->assertTrue($player->hasRole('role1'));
        $this->assertFalse($player->hasRole('role2'));
    }

    public function testAddGetRemoveCharacter()
    {
        $play = new Player();
        $c1 = new Character();
        $c2 = new Character();

        $this->assertSame([], $play->getCharacters());

        $play->addCharacter($c1);
        $play->addCharacter($c2);
        $this->assertSame([$c1, $c2], $play->getCharacters());

        $play->removeCharacter($c2);
        $this->assertSame([$c1], $play->getCharacters());
    }

    public function testAddGetRemoveApplication()
    {
        $play = new Player();
        $a1 = new Group();
        $a2 = new Group();

        $this->assertSame([], $play->getApplications());

        $play->addApplication($a1);
        $play->addApplication($a2);
        $this->assertSame([$a1, $a2], $play->getApplications());

        $play->removeApplication($a2);
        $this->assertSame([$a1], $play->getApplications());
    }

    public function testAddGetRemoveGroup()
    {
        $play = new Player();
        $g1 = new Group();
        $g2 = new Group();

        $this->assertSame([], $play->getGroups());

        $play->addGroup($g1);
        $play->addGroup($g2);
        $this->assertSame([$g1, $g2], $play->getGroups());

        $play->removeGroup($g2);
        $this->assertSame([$g1], $play->getGroups());
    }

    public function testAddGetRemoveManagerGroups()
    {
        $play = new Player();
        $g1 = new Group();
        $g2 = new Group();

        $this->assertSame([], $play->getManagerGroups());

        $play->addManagerGroup($g1);
        $play->addManagerGroup($g2);
        $this->assertSame([$g1, $g2], $play->getManagerGroups());

        $play->removeManagerGroup($g2);
        $this->assertSame([$g1], $play->getManagerGroups());
    }

    public function testAddGetRemoveManagerApps()
    {
        $play = new Player();
        $a1 = new App();
        $a2 = new App();

        $this->assertSame([], $play->getManagerApps());

        $play->addManagerApp($a1);
        $play->addManagerApp($a2);
        $this->assertSame([$a1, $a2], $play->getManagerApps());

        $play->removeManagerApp($a2);
        $this->assertSame([$a1], $play->getManagerApps());
    }
}
