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
    public function testSetGetId()
    {
        $watchlist = new Watchlist();
        $watchlist->setId(1);
        $this->assertSame(1, $watchlist->getId());
    }

    public function testSetGetName()
    {
        $watchlist = new Watchlist();
        $watchlist->setName('name');
        $this->assertSame('name', $watchlist->getName());
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

    public function testAddGetRemoveBlacklistCorporation()
    {
        $watchlist = new Watchlist();
        $e1 = new Corporation();
        $e2 = new Corporation();

        $this->assertSame([], $watchlist->getBlacklistCorporations());

        $watchlist->addBlacklistCorporation($e1);
        $watchlist->addBlacklistCorporation($e2);
        $this->assertSame([$e1], $watchlist->getBlacklistCorporations());

        $watchlist->removeBlacklistCorporation($e1);
        $this->assertSame([], $watchlist->getBlacklistCorporations());
    }

    public function testAddGetRemoveBlacklistAlliance()
    {
        $watchlist = new Watchlist();
        $e1 = new Alliance();
        $e2 = new Alliance();

        $this->assertSame([], $watchlist->getBlacklistAlliances());

        $watchlist->addBlacklistAlliance($e1);
        $watchlist->addBlacklistAlliance($e2);
        $this->assertSame([$e1], $watchlist->getBlacklistAlliances());

        $watchlist->removeBlacklistAlliance($e1);
        $this->assertSame([], $watchlist->getBlacklistAlliances());
    }

    public function testAddGetRemoveWhitelistCorporation()
    {
        $watchlist = new Watchlist();
        $e1 = new Corporation();
        $e2 = new Corporation();

        $this->assertSame([], $watchlist->getWhitelistCorporations());

        $watchlist->addWhitelistCorporation($e1);
        $watchlist->addWhitelistCorporation($e2);
        $this->assertSame([$e1], $watchlist->getWhitelistCorporations());

        $watchlist->removeWhitelistCorporation($e1);
        $this->assertSame([], $watchlist->getWhitelistCorporations());
    }

    public function testAddGetRemoveWhitelistAlliance()
    {
        $watchlist = new Watchlist();
        $e1 = new Alliance();
        $e2 = new Alliance();

        $this->assertSame([], $watchlist->getWhitelistAlliances());

        $watchlist->addWhitelistAlliance($e1);
        $watchlist->addWhitelistAlliance($e2);
        $this->assertSame([$e1], $watchlist->getWhitelistAlliances());

        $watchlist->removeWhitelistAlliance($e1);
        $this->assertSame([], $watchlist->getWhitelistAlliances());
    }
}
