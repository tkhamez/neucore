<?php declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
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
            'player' => ['id' => null, 'name' => 'p'],
            'group' => ['id' => null, 'name' => 'g', 'visibility' => Group::VISIBILITY_PRIVATE],
            'status' => GroupApplication::STATUS_PENDING,
            'created' => '2019-04-06T16:09:24Z'
        ], json_decode((string) json_encode($groupApp), true));
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
        $dt1 = new \DateTime('2019-04-06 16:09:25');

        $groupApp = new GroupApplication();
        $groupApp->setCreated($dt1);
        $dt2 = $groupApp->getCreated();

        $this->assertNotSame($dt1, $dt2);
        $this->assertSame('2019-04-06T16:09:25+00:00', $dt2->format(\DateTime::ATOM));
    }

    public function testSetGetStatus()
    {
        $groupApp = new GroupApplication();

        $groupApp->setStatus('invalid');
        $this->assertSame($groupApp->getStatus(), GroupApplication::STATUS_PENDING);

        $groupApp->setStatus(GroupApplication::STATUS_DENIED);
        $this->assertSame($groupApp->getStatus(), GroupApplication::STATUS_DENIED);
    }
}
