<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Service\ObjectManager;
use Tests\Helper;
use Tests\TestLogger;
use Tests\WriteErrorListener;

class ObjectManagerTest extends \PHPUnit\Framework\TestCase
{
    public function testFlush()
    {
        $om = new ObjectManager((new Helper())->getEm(), new TestLogger('Test'));

        $this->assertTrue($om->flush());
    }

    public function testFlushException()
    {
        $em = (new Helper())->getEm(true);
        $em->getEventManager()->addEventListener(\Doctrine\ORM\Events::onFlush, new WriteErrorListener());

        $log = new TestLogger('Test');

        $om = new ObjectManager($em, $log);

        $this->assertFalse($om->flush());
        $this->assertSame('error', $log->getHandler()->getRecords()[0]['message']);
    }
}
