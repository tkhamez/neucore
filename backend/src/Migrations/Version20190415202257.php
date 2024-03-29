<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190415202257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        // delete characters without a player - there should *not* be any!
        $this->addSql('DELETE FROM characters WHERE player_id IS NULL');

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE characters CHANGE player_id player_id INT NOT NULL');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE characters CHANGE player_id player_id INT DEFAULT NULL');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
