<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version20230101183432 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->connection->executeQuery(
            "UPDATE system_variables SET name = ? WHERE name = ?",
            ['allow_login_no_scopes', 'allow_login_managed'],
        );
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeQuery(
            "UPDATE system_variables SET name = ? WHERE name = ?",
            ['allow_login_managed', 'allow_login_no_scopes'],
        );
    }
}
