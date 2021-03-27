<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210327162922 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE character_name_changes (id INT AUTO_INCREMENT NOT NULL, character_id BIGINT NOT NULL, old_name VARCHAR(255) NOT NULL, change_date DATETIME NOT NULL, INDEX IDX_F72F78EA1136BE75 (character_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE character_name_changes ADD CONSTRAINT FK_F72F78EA1136BE75 FOREIGN KEY (character_id) REFERENCES characters (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE character_name_changes');
    }
}
