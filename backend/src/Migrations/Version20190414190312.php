<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190414190312 extends AbstractMigration
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

        $this->addSql('CREATE TABLE group_required_groups (group_source INT NOT NULL, group_target INT NOT NULL, INDEX IDX_1B2DD19D8C0CA7A3 (group_source), INDEX IDX_1B2DD19D95E9F72C (group_target), PRIMARY KEY(group_source, group_target)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE group_required_groups ADD CONSTRAINT FK_1B2DD19D8C0CA7A3 FOREIGN KEY (group_source) REFERENCES groups_tbl (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_required_groups ADD CONSTRAINT FK_1B2DD19D95E9F72C FOREIGN KEY (group_target) REFERENCES groups_tbl (id) ON DELETE CASCADE');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('DROP TABLE group_required_groups');
    }
}
