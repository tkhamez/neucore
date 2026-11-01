<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Removes deprecated integer display widths (e.g. TINYINT(1), INT(11)) from
 * columns that were created with them in older migrations, so that existing
 * installations get the same column definitions as new installations.
 */
final class Version20261101145000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE characters MODIFY COLUMN main TINYINT NOT NULL');
        $this->addSql('ALTER TABLE players MODIFY COLUMN deactivation_mail_sent TINYINT NOT NULL');
        $this->addSql('ALTER TABLE roles MODIFY COLUMN id INT NOT NULL');
        $this->addSql('ALTER TABLE corporations MODIFY COLUMN auto_allowlist TINYINT DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE watchlists MODIFY COLUMN lock_watchlist_settings TINYINT NOT NULL');
        $this->addSql('ALTER TABLE groups_tbl MODIFY COLUMN auto_accept TINYINT NOT NULL');
        $this->addSql('ALTER TABLE esi_tokens MODIFY COLUMN valid_token TINYINT DEFAULT NULL');
        $this->addSql('ALTER TABLE esi_tokens MODIFY COLUMN has_roles TINYINT DEFAULT NULL');
        $this->addSql('ALTER TABLE groups_tbl MODIFY COLUMN is_default TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // no down() migration, as requested
    }
}
