<?php

/** @noinspection PhpUnused */
/** @noinspection SqlResolve */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210123141218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX day_idx ON app_requests');
        $this->addSql(
            'ALTER TABLE app_requests ADD year INT NOT NULL, ADD month INT NOT NULL, ADD day_of_month INT NOT NULL'
        );

        /** @noinspection SqlWithoutWhere */
        $this->addSql('UPDATE app_requests SET year = YEAR(day), month = MONTH(day), day_of_month = DAY(day)');

        $this->addSql('ALTER TABLE app_requests DROP day');
        $this->addSql('CREATE INDEX ar_year_idx ON app_requests (year)');
        $this->addSql('CREATE INDEX ar_month_idx ON app_requests (month)');
        $this->addSql('DROP INDEX year_idx ON player_logins');
        $this->addSql('CREATE INDEX pl_year_idx ON player_logins (year)');
        $this->addSql('DROP INDEX month_idx ON player_logins');
        $this->addSql('CREATE INDEX pl_month_idx ON player_logins (month)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX ar_year_idx ON app_requests');
        $this->addSql('DROP INDEX ar_month_idx ON app_requests');
        $this->addSql(
            'ALTER TABLE app_requests ADD day VARCHAR(10) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`'
        );

        /** @noinspection SqlWithoutWhere */
        $this->addSql(
            'UPDATE app_requests SET day = CONCAT(year, "-", LPAD(month, 2, 0), "-", LPAD(day_of_month, 2, 0))'
        );

        $this->addSql('ALTER TABLE app_requests DROP year, DROP month, DROP day_of_month');
        $this->addSql('CREATE INDEX day_idx ON app_requests (day)');
        $this->addSql('DROP INDEX pl_year_idx ON player_logins');
        $this->addSql('CREATE INDEX year_idx ON player_logins (year)');
        $this->addSql('DROP INDEX pl_month_idx ON player_logins');
        $this->addSql('CREATE INDEX month_idx ON player_logins (month)');
    }
}
