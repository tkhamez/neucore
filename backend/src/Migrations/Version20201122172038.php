<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201122172038 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE player_logins (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, year INT NOT NULL, month INT NOT NULL, count INT NOT NULL, INDEX IDX_9913417A99E6F5DF (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE player_logins ADD CONSTRAINT FK_9913417A99E6F5DF FOREIGN KEY (player_id) REFERENCES players (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE player_logins');
    }
}
