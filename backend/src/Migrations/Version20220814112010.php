<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220814112010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE role_required_group (role_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_D21A6C44D60322AC (role_id), INDEX IDX_D21A6C44FE54D947 (group_id), PRIMARY KEY(role_id, group_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE role_required_group ADD CONSTRAINT FK_D21A6C44D60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE role_required_group ADD CONSTRAINT FK_D21A6C44FE54D947 FOREIGN KEY (group_id) REFERENCES groups_tbl (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE role_required_group');
    }
}
