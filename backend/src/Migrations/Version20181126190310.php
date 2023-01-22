<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181126190310 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('CREATE TABLE removed_characters (id INT AUTO_INCREMENT NOT NULL, player_id INT DEFAULT NULL, character_id BIGINT NOT NULL, character_name VARCHAR(255) NOT NULL, removed_date DATETIME NOT NULL, INDEX IDX_2987D70C99E6F5DF (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE removed_characters ADD CONSTRAINT FK_2987D70C99E6F5DF FOREIGN KEY (player_id) REFERENCES players (id)');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('DROP TABLE removed_characters');
    }
}
