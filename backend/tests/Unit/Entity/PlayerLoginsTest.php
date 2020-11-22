<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\Player;
use Neucore\Entity\PlayerLogins;
use PHPUnit\Framework\TestCase;

class PlayerLoginsTest extends TestCase
{
    public function testGetId()
    {
        $this->assertNull((new PlayerLogins())->getId());
    }

    public function testSetGetPlayer()
    {
        $pl = new PlayerLogins();
        $this->assertNull($pl->getPlayer());

        $pl->setPlayer((new Player())->setName('p'));
        $this->assertInstanceOf(Player::class, $pl->getPlayer());
        $this->assertSame('p', $pl->getPlayer()->getName());
    }

    public function testSetGetYear()
    {
        $pl = new PlayerLogins();
        $this->assertNull($pl->getYear());

        $pl->setYear(2020);
        $this->assertSame(2020, $pl->getYear());
    }

    public function testSetGetMonth()
    {
        $pl = new PlayerLogins();
        $this->assertNull($pl->getMonth());

        $pl->setMonth(11);
        $this->assertSame(11, $pl->getMonth());
    }

    public function testSetGetCount()
    {
        $pl = new PlayerLogins();
        $this->assertNull($pl->getCount());

        $pl->setCount(31);
        $this->assertSame(31, $pl->getCount());
    }
}
