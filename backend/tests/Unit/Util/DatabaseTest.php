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

    /**
     * @see PlayerRepositoryTest::testFindCharacters_SpecialChars
     */
    public function testEscapeForLike()
    {
        $this->assertSame('T\_s\%\\\\t', Database::escapeForLike('T_s%\t'));
    }
}
