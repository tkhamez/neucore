<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210730144706 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add columns
        $this->addSql('ALTER TABLE esi_tokens ADD valid_token TINYINT(1) DEFAULT NULL, 
                        ADD valid_token_time DATETIME DEFAULT NULL');

        // Copy data
        $loginId = 1;
        /** @noinspection SqlResolve */
        $this->addSql(
            "UPDATE esi_tokens AS dest,
            (SELECT id, valid_token, valid_token_time FROM characters) AS src
            SET dest.valid_token = src.valid_token, dest.valid_token_time = src.valid_token_time
            WHERE dest.eve_login_id = $loginId AND dest.character_id = src.id",
        );

        // Drop columns
        /** @noinspection SqlResolve */
        $this->addSql('ALTER TABLE characters DROP valid_token, DROP valid_token_time');
    }

    public function down(Schema $schema): void
    {
        // Add columns
        $this->addSql('ALTER TABLE characters ADD valid_token TINYINT(1) DEFAULT NULL, 
                        ADD valid_token_time DATETIME DEFAULT NULL');

        // Copy data
        $loginId = 1;
        /** @noinspection SqlResolve */
        $this->addSql(
            "UPDATE characters AS dest,
            (SELECT character_id, valid_token, valid_token_time FROM esi_tokens WHERE eve_login_id = $loginId) AS src
            SET dest.valid_token = src.valid_token, dest.valid_token_time = src.valid_token_time
            WHERE dest.id = src.character_id",
        );

        // Drop columns
        $this->addSql('ALTER TABLE esi_tokens DROP valid_token, DROP valid_token_time');
    }
}
