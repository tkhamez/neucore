<?php declare(strict_types=1);

namespace Tests\Unit\Core\Entity;

use Brave\Core\Entity\Group;
use Brave\Core\Entity\GroupApplication;
use Brave\Core\Entity\Player;
use PHPUnit\Framework\TestCase;

class GroupApplicationTest extends TestCase
{
    public function testJsonSerialize()
    {
        $groupApp = new GroupApplication();
        $groupApp->setPlayer((new Player)->setName('p'));
        $groupApp->setGroup((new Group)->setName('g'));
        $groupApp->setCreated(new \DateTime('2019-04-06 16:09:24'));

        $this->assertSame([
            'id' => null,
            'player' => [
                'id' => null,
                'name' => 'p',
                'status' => Player::STATUS_STANDARD,
                'roles' => [],
                'characters' => [],
                'groups' => [],
                'managerGroups' => [],
                'managerApps' => [],
            ],
            'group' => ['id' => null, 'name' => 'g', 'visibility' => Group::VISIBILITY_PRIVATE],
            'created' => '2019-04-06T16:09:24Z'
        ], json_decode(json_encode($groupApp), true));
    }

    public function testGetId()
    {
        $this->assertNull((new GroupApplication)->getId());
    }

    public function testSetGetPlayer()
    {
        $groupApp = new GroupApplication();
        $player = new Player();
        $groupApp->setPlayer($player);
        $this->assertSame($player, $groupApp->getPlayer());
    }

    public function testSetGetGroup()
    {
        $groupApp = new GroupApplication();
        $group = new Group();
        $groupApp->setGroup($group);
        $this->assertSame($group, $groupApp->getGroup());
    }

    public function testSetGetCreated()
    {
        $dt1 = date_create('2019-04-06 16:09:25');

        $groupApp = new GroupApplication();
        $groupApp->setCreated($dt1);
        $dt2 = $groupApp->getCreated();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2019-04-06T16:09:25+00:00', $dt2->format(\DateTime::ATOM));
    }
}
