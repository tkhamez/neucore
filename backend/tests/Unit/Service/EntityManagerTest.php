<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\ORM\Events;
use Neucore\Service\EntityManager;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class EntityManagerTest extends TestCase
{
    public function testFlush()
    {
        $em = new EntityManager((new Helper())->getEm(), new Logger('Test'));

        $this->assertTrue($em->flush());
    }

    public function testFlushException()
    {
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(Events::onFlush, new WriteErrorListener());

        $log = new Logger('Test');

        $om = new EntityManager($em, $log);

        $this->assertFalse($om->flush());
        $this->assertSame('error', $log->getHandler()->getRecords()[0]['message']);
    }
}
