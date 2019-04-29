<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Service\ObjectManager;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class ObjectManagerTest extends \PHPUnit\Framework\TestCase
{
    public function testFlush()
    {
        $om = new ObjectManager((new Helper())->getEm(), new Logger('Test'));

        $this->assertTrue($om->flush());
    }

    public function testFlushException()
    {
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());

        $log = new Logger('Test');

        $om = new ObjectManager($em, $log);

        $this->assertFalse($om->flush());
        $this->assertSame('error', $log->getHandler()->getRecords()[0]['message']);
    }
}
