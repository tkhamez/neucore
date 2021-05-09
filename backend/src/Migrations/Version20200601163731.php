<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200601163731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE corporation_members DROP FOREIGN KEY FK_4E085D341136BE75');
        $this->addSql('ALTER TABLE corporation_members ADD CONSTRAINT FK_4E085D341136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE corporation_members DROP FOREIGN KEY FK_4E085D341136BE75');
        $this->addSql('ALTER TABLE corporation_members ADD CONSTRAINT FK_4E085D341136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
