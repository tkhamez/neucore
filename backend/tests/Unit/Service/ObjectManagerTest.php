<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Neucore\Service\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class ObjectManagerTest extends TestCase
{
    /**
     * @var WriteErrorListener
     */
    private static $writeErrorListener;

    /**
     * @var EntityManagerInterface
     */
    private static $em;

    public static function setupBeforeClass(): void
    {
        self::$writeErrorListener = new WriteErrorListener();
        self::$em = (new Helper())->getEm();
    }

    public function tearDown(): void
    {
        self::$em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testFlush()
    {
        $om = new ObjectManager(self::$em, new Logger('Test'));

        $this->assertTrue($om->flush());
    }

    public function testFlushException()
    {
        self::$em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $log = new Logger('Test');

        $om = new ObjectManager(self::$em, $log);

        $this->assertFalse($om->flush());
        $this->assertSame('error', $log->getHandler()->getRecords()[0]['message']);
    }
}
