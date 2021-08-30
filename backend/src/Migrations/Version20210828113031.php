<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210828113031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_eve_login (app_id INT NOT NULL, evelogin_id INT NOT NULL, INDEX IDX_7AD1F20A7987212D (app_id), INDEX IDX_7AD1F20A9E378596 (evelogin_id), PRIMARY KEY(app_id, evelogin_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_eve_login ADD CONSTRAINT FK_7AD1F20A7987212D FOREIGN KEY (app_id) REFERENCES apps (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_eve_login ADD CONSTRAINT FK_7AD1F20A9E378596 FOREIGN KEY (evelogin_id) REFERENCES eve_logins (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE app_eve_login');
    }
}
