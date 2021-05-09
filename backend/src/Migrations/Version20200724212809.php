<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200724212809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE corporations CHANGE auto_whitelist auto_allowlist TINYINT(1) DEFAULT \'0\' NOT NULL');

        $this->addSql('RENAME TABLE watchlist_blacklist_alliance TO watchlist_kicklist_alliance');
        $this->addSql('RENAME TABLE watchlist_blacklist_corporation TO watchlist_kicklist_corporation');
        $this->addSql('RENAME TABLE watchlist_whitelist_alliance TO watchlist_allowlist_alliance');
        $this->addSql('RENAME TABLE watchlist_whitelist_corporation TO watchlist_allowlist_corporation');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('RENAME TABLE watchlist_kicklist_alliance TO watchlist_blacklist_alliance');
        $this->addSql('RENAME TABLE watchlist_kicklist_corporation TO watchlist_blacklist_corporation');
        $this->addSql('RENAME TABLE watchlist_allowlist_alliance TO watchlist_whitelist_alliance');
        $this->addSql('RENAME TABLE watchlist_allowlist_corporation TO watchlist_whitelist_corporation');

        $this->addSql('ALTER TABLE corporations CHANGE auto_allowlist auto_whitelist TINYINT(1) DEFAULT \'0\' NOT NULL');
    }
}
