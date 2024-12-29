<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20221008152421 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->connection->executeQuery(
            "UPDATE system_variables SET name = ? WHERE name = ?",
            ['rate_limit_app_max_requests', 'api_rate_limit_max_requests'],
        );
        $this->connection->executeQuery(
            "UPDATE system_variables SET name = ? WHERE name = ?",
            ['rate_limit_app_reset_time', 'api_rate_limit_reset_time'],
        );
        $this->connection->executeQuery(
            "UPDATE system_variables SET name = ? WHERE name = ?",
            ['rate_limit_app_active', 'api_rate_limit_active'],
        );
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeQuery(
            "UPDATE system_variables SET name = ? WHERE name = ?",
            ['api_rate_limit_max_requests', 'rate_limit_app_max_requests'],
        );
        $this->connection->executeQuery(
            "UPDATE system_variables SET name = ? WHERE name = ?",
            ['api_rate_limit_reset_time', 'rate_limit_app_reset_time'],
        );
        $this->connection->executeQuery(
            "UPDATE system_variables SET name = ? WHERE name = ?",
            ['api_rate_limit_active', 'rate_limit_app_active'],
        );
    }
}
