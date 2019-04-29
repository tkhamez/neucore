<?php

declare(strict_types=1);

namespace Brave\Core\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190415202257 extends AbstractMigration
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

        // delete characters without a player - there should *not* be any!
        $this->addSql('DELETE FROM characters WHERE player_id IS NULL');

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE characters CHANGE player_id player_id INT NOT NULL');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE characters CHANGE player_id player_id INT DEFAULT NULL');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
