<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220108152202 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE group_forbidden_groups (group_source INT NOT NULL, group_target INT NOT NULL, INDEX IDX_C09FA89E8C0CA7A3 (group_source), INDEX IDX_C09FA89E95E9F72C (group_target), PRIMARY KEY(group_source, group_target)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE group_forbidden_groups ADD CONSTRAINT FK_C09FA89E8C0CA7A3 FOREIGN KEY (group_source) REFERENCES groups_tbl (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_forbidden_groups ADD CONSTRAINT FK_C09FA89E95E9F72C FOREIGN KEY (group_target) REFERENCES groups_tbl (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE group_forbidden_groups');
    }
}
