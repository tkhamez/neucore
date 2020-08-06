<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Watchlist;
use PHPUnit\Framework\TestCase;

class WatchlistTest extends TestCase
{
    public function testJsonSerialize()
    {
        $this->assertSame(
            ['id' => null, 'name' => 'name', 'lockWatchlistSettings' => false],
            (new Watchlist())->setName('name')->jsonSerialize()
        );
    }

    public function testSetGetName()
    {
        $watchlist = new Watchlist();
        $watchlist->setName('name');
        $this->assertSame('name', $watchlist->getName());
    }

    public function testSetGetLockWatchlistSettings()
    {
        $watchlist = new Watchlist();
        $watchlist->setLockWatchlistSettings(true);
        $this->assertTrue($watchlist->getLockWatchlistSettings());
    }

    public function testAddGetRemoveExemptions()
    {
        $watchlist = new Watchlist();
        $p1 = new Player();
        $p2 = new Player();

        $this->assertSame([], $watchlist->getExemptions());

        $watchlist->addExemption($p1);
        $watchlist->addExemption($p2);
        $this->assertSame([$p1], $watchlist->getExemptions());

        $watchlist->removeExemption($p1);
        $this->assertSame([], $watchlist->getExemptions());
    }

    public function testAddGetRemoveCorporation()
    {
        $watchlist = new Watchlist();
        $c1 = new Corporation();
        $c2 = new Corporation();

        $this->assertSame([], $watchlist->getCorporations());

        $watchlist->addCorporation($c1);
        $watchlist->addCorporation($c2);
        $this->assertSame([$c1], $watchlist->getCorporations());

        $watchlist->removeCorporation($c1);
        $this->assertSame([], $watchlist->getCorporations());
    }

    public function testAddGetRemoveAlliance()
    {
        $watchlist = new Watchlist();
        $a1 = new Alliance();
        $a2 = new Alliance();

        $this->assertSame([], $watchlist->getAlliances());

        $watchlist->addAlliance($a1);
        $watchlist->addAlliance($a2);
        $this->assertSame([$a1], $watchlist->getAlliances());

        $watchlist->removeAlliance($a1);
        $this->assertSame([], $watchlist->getAlliances());
    }

    public function testAddGetRemoveGroup()
    {
        $watchlist = new Watchlist();
        $group1 = new Group();
        $group2 = new Group();

        $this->assertSame([], $watchlist->getGroups());

        $watchlist->addGroup($group1);
        $watchlist->addGroup($group2);
        $this->assertSame([$group1], $watchlist->getGroups());

        $watchlist->removeGroup($group1);
        $this->assertSame([], $watchlist->getGroups());
    }

    public function testAddGetRemoveManagerGroup()
    {
        $watchlist = new Watchlist();
        $group1 = new Group();
        $group2 = new Group();

        $this->assertSame([], $watchlist->getManagerGroups());

        $watchlist->addManagerGroup($group1);
        $watchlist->addManagerGroup($group2);
        $this->assertSame([$group1], $watchlist->getManagerGroups());

        $watchlist->removeManagerGroup($group1);
        $this->assertSame([], $watchlist->getManagerGroups());
    }

    public function testAddGetRemoveKicklistCorporation()
    {
        $watchlist = new Watchlist();
        $e1 = new Corporation();
        $e2 = new Corporation();

        $this->assertSame([], $watchlist->getKicklistCorporations());

        $watchlist->addKicklistCorporation($e1);
        $watchlist->addKicklistCorporation($e2);
        $this->assertSame([$e1], $watchlist->getKicklistCorporations());

        $watchlist->removeKicklistCorporation($e1);
        $this->assertSame([], $watchlist->getKicklistCorporations());
    }

    public function testAddGetRemoveKicklistAlliance()
    {
        $watchlist = new Watchlist();
        $e1 = new Alliance();
        $e2 = new Alliance();

        $this->assertSame([], $watchlist->getKicklistAlliances());

        $watchlist->addKicklistAlliance($e1);
        $watchlist->addKicklistAlliance($e2);
        $this->assertSame([$e1], $watchlist->getKicklistAlliances());

        $watchlist->removeKicklistAlliance($e1);
        $this->assertSame([], $watchlist->getKicklistAlliances());
    }

    public function testAddGetRemoveAllowlistCorporation()
    {
        $watchlist = new Watchlist();
        $e1 = (new Corporation())->setAutoAllowlist(true);
        $e2 = new Corporation();

        $this->assertSame([], $watchlist->getAllowlistCorporations());

        $watchlist->addAllowlistCorporation($e1);
        $watchlist->addAllowlistCorporation($e2);
        $this->assertSame([$e1], $watchlist->getAllowlistCorporations());

        $watchlist->removeAllowlistCorporation($e1);
        $this->assertSame([], $watchlist->getAllowlistCorporations());
        $this->assertFalse($e1->getAutoAllowlist());
    }

    public function testAddGetRemoveAllowlistAlliance()
    {
        $watchlist = new Watchlist();
        $e1 = new Alliance();
        $e2 = new Alliance();

        $this->assertSame([], $watchlist->getAllowlistAlliances());

        $watchlist->addAllowlistAlliance($e1);
        $watchlist->addAllowlistAlliance($e2);
        $this->assertSame([$e1], $watchlist->getAllowlistAlliances());

        $watchlist->removeAllowlistAlliance($e1);
        $this->assertSame([], $watchlist->getAllowlistAlliances());
    }
}
