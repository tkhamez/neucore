<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190811124721 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE removed_characters ADD deleted_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE removed_characters ADD CONSTRAINT FK_129104F71F6FA0AF FOREIGN KEY (deleted_by) REFERENCES players (id)');
        $this->addSql('CREATE INDEX IDX_129104F71F6FA0AF ON removed_characters (deleted_by)');
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE removed_characters DROP FOREIGN KEY FK_129104F71F6FA0AF');
        $this->addSql('DROP INDEX IDX_129104F71F6FA0AF ON removed_characters');
        $this->addSql('ALTER TABLE removed_characters DROP deleted_by');
    }
}
