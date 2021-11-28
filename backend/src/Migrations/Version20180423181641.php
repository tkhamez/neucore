<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180423181641 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE characters DROP FOREIGN KEY FK_3A29410E10A0EA3F');
        $this->addSql('DROP INDEX IDX_3A29410E10A0EA3F ON characters');
        $this->addSql('ALTER TABLE characters DROP alliance_id');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE characters ADD alliance_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE characters ADD CONSTRAINT FK_3A29410E10A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id)');
        $this->addSql('CREATE INDEX IDX_3A29410E10A0EA3F ON characters (alliance_id)');
    }
}
