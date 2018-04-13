<?php
namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\App;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\Player;

class GroupTest extends \PHPUnit\Framework\TestCase
{

    public function testJsonSerialize()
    {
        $group = new Group();
        $group->setName('g.name');

        $this->assertSame(
            ['id' => null, 'name' => 'g.name'],
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
}
