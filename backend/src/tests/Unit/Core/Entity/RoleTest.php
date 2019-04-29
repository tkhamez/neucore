<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\App;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\Role;

class RoleTest extends \PHPUnit\Framework\TestCase
{
    public function testJsonSerialize()
    {
        $role = new Role(1);
        $role->setName('r.name');

        $this->assertSame('r.name', json_decode((string) json_encode($role), true));
    }

    public function testGetId()
    {
        $this->assertSame(1, (new Role(1))->getId());
    }

    public function testSetGetName()
    {
        $role = new Role(1);
        $role->setName('nam');
        $this->assertSame('nam', $role->getName());
    }

    public function testAddGetRemoveCharacter()
    {
        $role = new Role(1);
        $p1 = new Player();
        $p2 = new Player();

        $this->assertSame([], $role->getPlayers());

        $role->addPlayer($p1);
        $role->addPlayer($p2);
        $this->assertSame([$p1, $p2], $role->getPlayers());

        $role->removePlayer($p2);
        $role->removePlayer($p1);
        $this->assertSame([], $role->getPlayers());
    }

    public function testAddGetRemoveApp()
    {
        $role = new Role(1);
        $a1 = new App();
        $a2 = new App();

        $this->assertSame([], $role->getApps());

        $role->addApp($a1);
        $role->addApp($a2);
        $this->assertSame([$a1, $a2], $role->getApps());

        $role->removeApp($a2);
        $this->assertSame([$a1], $role->getApps());
    }
}
