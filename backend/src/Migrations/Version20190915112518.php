<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190915112518 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('CREATE TABLE corporation_group_tracking (corporation_id BIGINT NOT NULL, group_id INT NOT NULL, INDEX IDX_17E61049B2685369 (corporation_id), INDEX IDX_17E61049FE54D947 (group_id), PRIMARY KEY(corporation_id, group_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE corporation_group_tracking ADD CONSTRAINT FK_17E61049B2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE corporation_group_tracking ADD CONSTRAINT FK_17E61049FE54D947 FOREIGN KEY (group_id) REFERENCES groups_tbl (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('DROP TABLE corporation_group_tracking');
    }
}
