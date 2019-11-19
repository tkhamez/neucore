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

    public function testAddGetRemoveManager()
    {
        $watchlist = new Watchlist();
        $p1 = new Player();
        $p2 = new Player();

        $this->assertSame([], $watchlist->getExemptions());

        $watchlist->addExemption($p1);
        $watchlist->addExemption($p2);
        $this->assertSame([$p1, $p2], $watchlist->getExemptions());

        $watchlist->removeExemption($p2);
        $this->assertSame([$p1], $watchlist->getExemptions());
    }

    public function testAddGetRemoveCorporation()
    {
        $watchlist = new Watchlist();
        $c1 = new Corporation();
        $c2 = new Corporation();

        $this->assertSame([], $watchlist->getCorporations());

        $watchlist->addCorporation($c1);
        $watchlist->addCorporation($c2);
        $this->assertSame([$c1, $c2], $watchlist->getCorporations());

        $watchlist->removeCorporation($c2);
        $this->assertSame([$c1], $watchlist->getCorporations());
    }

    public function testAddGetRemoveAlliance()
    {
        $watchlist = new Watchlist();
        $a1 = new Alliance();
        $a2 = new Alliance();

        $this->assertSame([], $watchlist->getAlliances());

        $watchlist->addAlliance($a1);
        $watchlist->addAlliance($a2);
        $this->assertSame([$a1, $a2], $watchlist->getAlliances());

        $watchlist->removeAlliance($a2);
        $this->assertSame([$a1], $watchlist->getAlliances());
    }

    public function testAddGetRemoveGroup()
    {
        $watchlist = new Watchlist();
        $required1 = new Group();
        $required2 = new Group();

        $this->assertSame([], $watchlist->getGroups());

        $watchlist->addGroup($required1);
        $watchlist->addGroup($required2);
        $this->assertSame([$required1, $required2], $watchlist->getGroups());

        $watchlist->removeGroup($required2);
        $this->assertSame([$required1], $watchlist->getGroups());
    }
}
