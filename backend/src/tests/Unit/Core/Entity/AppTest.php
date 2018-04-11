<?php

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\App;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\Role;

class AppTest extends \PHPUnit\Framework\TestCase
{
    public function testJsonSerialize()
    {
        $app = new App();
        $app->setName('test app');

        $this->assertSame([
            'id'   => null,
            'name' => 'test app',
        ], json_decode(json_encode($app), true));
    }

    public function testGetId()
    {
        $this->assertNull((new App())->getId());
    }

    public function testSetGetName()
    {
        $app = new App();
        $app->setName('nam');
        $this->assertSame('nam', $app->getName());
    }

    public function testSetGetSecret()
    {
        $app = new App();
        $app->setSecret('sec');
        $this->assertSame('sec', $app->getSecret());
    }

    public function testAddGetRemoveRole()
    {
        $app = new App();
        $r1 = new Role();
        $r2 = new Role();

        $this->assertSame([], $app->getRoles()->toArray());

        $app->addRole($r1);
        $app->addRole($r2);
        $this->assertSame([$r1, $r2], $app->getRoles()->toArray());

        $app->removeRole($r2);
        $this->assertSame([$r1], $app->getRoles()->toArray());
    }

    public function testAddGetRemoveGroup()
    {
        $app = new App();
        $g1 = new Group();
        $g2 = new Group();

        $this->assertSame([], $app->getGroups()->toArray());

        $app->addGroup($g1);
        $app->addGroup($g2);
        $this->assertSame([$g1, $g2], $app->getGroups()->toArray());

        $app->removeGroup($g2);
        $this->assertSame([$g1], $app->getGroups()->toArray());
    }

    public function testAddGetRemoveManager()
    {
        $app = new App();
        $p1 = new Player();
        $p2 = new Player();

        $this->assertSame([], $app->getManagers()->toArray());

        $app->addManager($p1);
        $app->addManager($p2);
        $this->assertSame([$p1, $p2], $app->getManagers()->toArray());

        $app->removeManager($p2);
        $this->assertSame([$p1], $app->getManagers()->toArray());
    }
}
