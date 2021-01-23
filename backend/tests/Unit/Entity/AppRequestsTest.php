<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use Neucore\Entity\App;
use Neucore\Entity\AppRequests;
use PHPUnit\Framework\TestCase;

class AppRequestsTest extends TestCase
{
    public function testGetId()
    {
        $this->assertNull((new AppRequests())->getId());
    }

    public function testSetGeApp()
    {
        $pl = new AppRequests();
        $this->assertNull($pl->getApp());

        $pl->setApp((new App())->setName('a'));
        $this->assertInstanceOf(App::class, $pl->getApp());
        $this->assertSame('a', $pl->getApp()->getName());
    }

    public function testSetGetYear()
    {
        $pl = new AppRequests();
        $this->assertNull($pl->getYear());
        $this->assertSame(2020, $pl->setYear(2020)->getYear());
    }

    public function testSetGetMonth()
    {
        $pl = new AppRequests();
        $this->assertNull($pl->getMonth());
        $this->assertSame(8, $pl->setMonth(8)->getMonth());
    }

    public function testSetGetDayOfMonth()
    {
        $pl = new AppRequests();
        $this->assertNull($pl->getDayOfMonth());
        $this->assertSame(16, $pl->setDayOfMonth(16)->getDayOfMonth());
    }

    public function testSetGetCount()
    {
        $pl = new AppRequests();
        $this->assertNull($pl->getCount());

        $pl->setCount(31);
        $this->assertSame(31, $pl->getCount());
    }
}
