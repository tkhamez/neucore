<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231110211804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->connection->executeQuery("INSERT INTO roles (id, name) VALUES (23, 'app-esi-login')");
        $this->connection->executeQuery("INSERT INTO roles (id, name) VALUES (24, 'app-esi-proxy')");
        $this->connection->executeQuery("INSERT INTO roles (id, name) VALUES (25, 'app-esi-token')");

        $esiApps = $this->connection
            ->executeQuery('SELECT app_id FROM app_role WHERE role_id = 12') // app-esi
            ->fetchAllAssociative();

        foreach ($esiApps as $esiApp) {
            $this->connection->executeQuery(
                "INSERT INTO app_role (app_id, role_id) VALUES (?, 23)",
                [$esiApp['app_id']],
            );
            $this->connection->executeQuery(
                "INSERT INTO app_role (app_id, role_id) VALUES (?, 24)",
                [$esiApp['app_id']],
            );
            $this->connection->executeQuery(
                "INSERT INTO app_role (app_id, role_id) VALUES (?, 25)",
                [$esiApp['app_id']],
            );
        }

        $this->connection->executeQuery("DELETE FROM app_role WHERE role_id = 12");
        $this->connection->executeQuery("DELETE FROM roles WHERE id = 12"); // app-esi
    }

    public function down(Schema $schema): void
    {
        $this->connection->executeQuery("INSERT INTO roles (id, name) VALUES (12, 'app-esi')");

        $esiApps = $this->connection
            ->executeQuery('SELECT app_id FROM app_role WHERE role_id = 23')
            ->fetchAllAssociative();

        foreach ($esiApps as $esiApp) {
            $this->connection->executeQuery(
                "INSERT INTO app_role (app_id, role_id) VALUES (?, 12)",
                [$esiApp['app_id']],
            );
        }

        $this->connection->executeQuery("DELETE FROM app_role WHERE role_id = 23");
        $this->connection->executeQuery("DELETE FROM app_role WHERE role_id = 24");
        $this->connection->executeQuery("DELETE FROM app_role WHERE role_id = 25");
        $this->connection->executeQuery("DELETE FROM roles WHERE id = 23");
        $this->connection->executeQuery("DELETE FROM roles WHERE id = 24");
        $this->connection->executeQuery("DELETE FROM roles WHERE id = 25");
    }
}
