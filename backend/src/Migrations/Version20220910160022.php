<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Neucore\Entity\EveLogin;

class Version20220910160022 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $eveLogin = $this->connection->executeQuery(
            'SELECT id FROM eve_logins WHERE name = ?',
            [EveLogin::NAME_DEFAULT],
        )->fetchAssociative();
        $eveLoginId = $eveLogin ? (int) ($eveLogin['id'] ?? 0) : 0;
        $this->abortIf(!$eveLoginId, 'Error: EVE login ' . EveLogin::NAME_DEFAULT . ' not found.');

        $this->connection->executeQuery(
            "INSERT INTO app_eve_login (app_id, evelogin_id) SELECT id, $eveLoginId FROM apps",
        );
    }

    public function down(Schema $schema): void
    {
        $eveLogin = $this->connection->executeQuery(
            'SELECT id FROM eve_logins WHERE name = ?',
            [EveLogin::NAME_DEFAULT],
        )->fetchAssociative();
        $eveLoginId = $eveLogin ? (int) ($eveLogin['id'] ?? 0) : 0;
        $this->abortIf(!$eveLoginId, 'Error: EVE login ' . EveLogin::NAME_DEFAULT . ' not found.');

        $this->connection->executeQuery("DELETE FROM app_eve_login WHERE evelogin_id = $eveLoginId");
    }
}
