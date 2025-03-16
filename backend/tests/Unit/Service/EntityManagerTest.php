<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Neucore\Service\EntityManager;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class EntityManagerTest extends TestCase
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
        $em = new EntityManager(self::$em, new Logger());

        $this->assertTrue($em->flush2());
    }

    public function testFlush2Exception()
    {
        self::$em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $log = new Logger();

        $om = new EntityManager(self::$em, $log);

        $this->assertFalse($om->flush2());
        $this->assertSame('error', $log->getHandler()->getRecords()[0]['message']);
    }
}
