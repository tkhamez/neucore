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

    public function testSetGetDay()
    {
        $pl = new AppRequests();
        $this->assertNull($pl->getDay());

        $pl->setDay('2020-11-22');
        $this->assertSame('2020-11-22', $pl->getDay());
    }

    public function testSetGetCount()
    {
        $pl = new AppRequests();
        $this->assertNull($pl->getCount());

        $pl->setCount(31);
        $this->assertSame(31, $pl->getCount());
    }
}
