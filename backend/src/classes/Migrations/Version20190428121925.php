<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190428121925 extends AbstractMigration
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

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE corporation_members CHANGE corporation_id corporation_id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE removed_characters CHANGE player_id player_id INT NOT NULL');
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE corporation_members CHANGE corporation_id corporation_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE removed_characters CHANGE player_id player_id INT DEFAULT NULL');
    }
}
