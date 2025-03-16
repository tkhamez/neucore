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
    private static WriteErrorListener $writeErrorListener;

    private static EntityManagerInterface $em;

    public static function setupBeforeClass(): void
    {
        self::$writeErrorListener = new WriteErrorListener();
        self::$em = (new Helper())->getEm();
    }

    public function tearDown(): void
    {
        self::$em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testFlush2()
    {
        $om = new ObjectManager(self::$em, new Logger());

        $this->assertTrue($om->flush2());
    }

    public function testFlush2Exception()
    {
        self::$em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $log = new Logger();

        $om = new ObjectManager(self::$em, $log);

        $this->assertFalse($om->flush2());
        $this->assertSame('error', $log->getHandler()->getRecords()[0]['message']);
    }
}
