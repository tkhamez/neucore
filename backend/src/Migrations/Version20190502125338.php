<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190502125338 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE system_variables CHANGE value value MEDIUMTEXT DEFAULT NULL COLLATE utf8_unicode_ci');

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE removed_characters DROP FOREIGN KEY FK_2987D70C99E6F5DF');
        $this->addSql('ALTER TABLE removed_characters DROP INDEX idx_2987d70c99e6f5df');
        $this->addSql('CREATE INDEX IDX_129104F799E6F5DF ON removed_characters (player_id)');
        $this->addSql('ALTER TABLE removed_characters ADD CONSTRAINT FK_129104F799E6F5DF FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        // this fails if data is too long:
        //$this->addSql('ALTER TABLE system_variables CHANGE value value TEXT DEFAULT NULL COLLATE utf8_unicode_ci');

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE removed_characters DROP FOREIGN KEY FK_129104F799E6F5DF');
        $this->addSql('ALTER TABLE removed_characters DROP INDEX IDX_129104F799E6F5DF');
        $this->addSql('CREATE INDEX idx_2987d70c99e6f5df ON removed_characters (player_id)');
        $this->addSql('ALTER TABLE removed_characters ADD CONSTRAINT FK_2987D70C99E6F5DF FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
