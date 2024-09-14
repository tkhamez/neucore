<?php

declare(strict_types=1);

namespace Neucore\Util;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;

class Database
{
    public const PLATFORM_MYSQL = 'mysql';

    public const PLATFORM_SQLITE = 'sqlite';

    public const PLATFORM_OTHER = 'other';

    public static function getDbName(EntityManagerInterface $em): string
    {
        try {
            $connection = $em->getConnection()->getDatabasePlatform();
        } catch (Exception) {
            return 'error';
        }
        if ($connection instanceof SQLitePlatform) {
            return self::PLATFORM_SQLITE;
        } elseif ($connection instanceof MySQLPlatform) {
            return self::PLATFORM_MYSQL;
        } elseif ($connection instanceof MariaDBPlatform) {
            return self::PLATFORM_MYSQL;
        } else {
            return self::PLATFORM_OTHER;
        }
    }

    public static function escapeForLike(EntityManagerInterface $em, string $query): string
    {
        if (self::getDbName($em) === self::PLATFORM_MYSQL) {
            return str_replace(['\\', '_', '%'], ['\\\\', '\_', '\%'], $query);
        }
        return $query;
    }
}
