<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Util\Database;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class DatabaseTest extends TestCase
{
    private static EntityManagerInterface $em;

    public static function setUpBeforeClass(): void
    {
        self::$em = (new Helper())->getEm();
    }

    public function testGetDbName()
    {
        $this->assertMatchesRegularExpression('/mysql|sqlite/', Database::getDbName(self::$em));
    }

    public function testEscapeForLike()
    {
        if (Database::getDbName(self::$em) === Database::PLATFORM_MYSQL) {
            $this->assertSame('T\_s\%\\\\t', Database::escapeForLike(self::$em, 'T_s%\t'));
        } else {
            $this->assertSame('T_s%\t', Database::escapeForLike(self::$em, 'T_s%\t'));
        }
    }
}
