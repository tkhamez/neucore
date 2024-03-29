<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210328201236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE character_name_changes DROP FOREIGN KEY FK_F72F78EA1136BE75');
        $this->addSql('ALTER TABLE character_name_changes ADD CONSTRAINT FK_F72F78EA1136BE75 FOREIGN KEY (character_id) REFERENCES characters (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE character_name_changes DROP FOREIGN KEY FK_F72F78EA1136BE75');
        $this->addSql('ALTER TABLE character_name_changes ADD CONSTRAINT FK_F72F78EA1136BE75 FOREIGN KEY (character_id) REFERENCES characters (id)');
    }
}
