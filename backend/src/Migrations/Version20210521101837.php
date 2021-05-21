<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210521101837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE corporation_members DROP FOREIGN KEY FK_4E085D341136BE75');
        $this->addSql('DROP INDEX UNIQ_4E085D341136BE75 ON corporation_members');
        $this->addSql('ALTER TABLE corporation_members DROP character_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE corporation_members ADD character_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE corporation_members ADD CONSTRAINT FK_4E085D341136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4E085D341136BE75 ON corporation_members (character_id)');
    }
}
