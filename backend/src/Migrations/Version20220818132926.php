<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220818132926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    /** @noinspection SqlResolve */
    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX ar_year_idx ON app_requests');
        $this->addSql('DROP INDEX ar_month_idx ON app_requests');
        $this->addSql('ALTER TABLE app_requests CHANGE id id BIGINT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE app_requests CHANGE year request_year INT NOT NULL');
        $this->addSql('ALTER TABLE app_requests CHANGE month request_month INT NOT NULL');
        $this->addSql('ALTER TABLE app_requests CHANGE day_of_month request_day_of_month INT NOT NULL');
        $this->addSql('ALTER TABLE app_requests CHANGE count request_count INT NOT NULL');
        $this->addSql('ALTER TABLE app_requests ADD request_hour INT NOT NULL');
        $this->addSql('CREATE INDEX ar_year_idx ON app_requests (request_year)');
        $this->addSql('CREATE INDEX ar_month_idx ON app_requests (request_month)');
        $this->addSql('CREATE INDEX ar_day_of_month_idx ON app_requests (request_day_of_month)');
        $this->addSql('CREATE INDEX ar_hour_idx ON app_requests (request_hour)');

        $this->addSql('DROP INDEX pl_year_idx ON player_logins');
        $this->addSql('DROP INDEX pl_month_idx ON player_logins');
        $this->addSql('ALTER TABLE player_logins CHANGE year request_year INT NOT NULL');
        $this->addSql('ALTER TABLE player_logins CHANGE month request_month INT NOT NULL');
        $this->addSql('ALTER TABLE player_logins CHANGE count request_count INT NOT NULL');
        $this->addSql('CREATE INDEX pl_year_idx ON player_logins (request_year)');
        $this->addSql('CREATE INDEX pl_month_idx ON player_logins (request_month)');

        $this->addSql('ALTER TABLE system_variables CHANGE value variable_value MEDIUMTEXT DEFAULT NULL');
    }

    /** @noinspection SqlResolve */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX ar_year_idx ON app_requests');
        $this->addSql('DROP INDEX ar_month_idx ON app_requests');
        $this->addSql('DROP INDEX ar_day_of_month_idx ON app_requests');
        $this->addSql('DROP INDEX ar_hour_idx ON app_requests');
        $this->addSql('ALTER TABLE app_requests CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE app_requests CHANGE request_year year INT NOT NULL');
        $this->addSql('ALTER TABLE app_requests CHANGE request_month month INT NOT NULL');
        $this->addSql('ALTER TABLE app_requests CHANGE request_day_of_month day_of_month INT NOT NULL');
        $this->addSql('ALTER TABLE app_requests CHANGE request_count count INT NOT NULL');
        $this->addSql('ALTER TABLE app_requests DROP request_hour');
        $this->addSql('CREATE INDEX ar_year_idx ON app_requests (year)');
        $this->addSql('CREATE INDEX ar_month_idx ON app_requests (month)');

        $this->addSql('DROP INDEX pl_year_idx ON player_logins');
        $this->addSql('DROP INDEX pl_month_idx ON player_logins');
        $this->addSql('ALTER TABLE player_logins CHANGE request_year year INT NOT NULL');
        $this->addSql('ALTER TABLE player_logins CHANGE request_month month INT NOT NULL');
        $this->addSql('ALTER TABLE player_logins CHANGE request_count count INT NOT NULL');
        $this->addSql('CREATE INDEX pl_year_idx ON player_logins (year)');
        $this->addSql('CREATE INDEX pl_month_idx ON player_logins (month)');

        $this->addSql('ALTER TABLE system_variables CHANGE variable_value value MEDIUMTEXT DEFAULT NULL');
    }
}
