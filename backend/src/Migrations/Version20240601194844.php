<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240601194844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX ar_month_idx ON app_requests');
        $this->addSql('DROP INDEX ar_day_of_month_idx ON app_requests');
        $this->addSql('DROP INDEX ar_hour_idx ON app_requests');
        $this->addSql('DROP INDEX ar_year_idx ON app_requests');
        $this->addSql('DROP INDEX character_eve_login_idx ON esi_tokens');
        $this->addSql('DROP INDEX player_group_idx ON group_applications');
        $this->addSql('DROP INDEX pl_year_idx ON player_logins');
        $this->addSql('DROP INDEX pl_month_idx ON player_logins');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX player_group_idx ON group_applications (player_id, group_id)');
        $this->addSql('CREATE INDEX ar_month_idx ON app_requests (request_month)');
        $this->addSql('CREATE INDEX ar_day_of_month_idx ON app_requests (request_day_of_month)');
        $this->addSql('CREATE INDEX ar_hour_idx ON app_requests (request_hour)');
        $this->addSql('CREATE INDEX ar_year_idx ON app_requests (request_year)');
        $this->addSql('CREATE UNIQUE INDEX character_eve_login_idx ON esi_tokens (character_id, eve_login_id)');
        $this->addSql('CREATE INDEX pl_year_idx ON player_logins (request_year)');
        $this->addSql('CREATE INDEX pl_month_idx ON player_logins (request_month)');
    }
}
