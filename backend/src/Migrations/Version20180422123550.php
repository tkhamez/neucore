<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180422123550 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('CREATE TABLE alliances (id BIGINT NOT NULL, name VARCHAR(255) NOT NULL, ticker VARCHAR(16) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE corporations (id BIGINT NOT NULL, alliance_id BIGINT DEFAULT NULL, name VARCHAR(255) NOT NULL, ticker VARCHAR(16) NOT NULL, INDEX IDX_6F3B37C710A0EA3F (alliance_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE corporation_group (corporation_id BIGINT NOT NULL, group_id INT NOT NULL, INDEX IDX_E13C0842B2685369 (corporation_id), INDEX IDX_E13C0842FE54D947 (group_id), PRIMARY KEY(corporation_id, group_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE corporations ADD CONSTRAINT FK_6F3B37C710A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id)');
        $this->addSql('ALTER TABLE corporation_group ADD CONSTRAINT FK_E13C0842B2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE corporation_group ADD CONSTRAINT FK_E13C0842FE54D947 FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE characters ADD corporation_id BIGINT DEFAULT NULL, ADD alliance_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE characters ADD CONSTRAINT FK_3A29410EB2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id)');
        $this->addSql('ALTER TABLE characters ADD CONSTRAINT FK_3A29410E10A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id)');
        $this->addSql('CREATE INDEX IDX_3A29410EB2685369 ON characters (corporation_id)');
        $this->addSql('CREATE INDEX IDX_3A29410E10A0EA3F ON characters (alliance_id)');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE characters DROP FOREIGN KEY FK_3A29410E10A0EA3F');
        $this->addSql('ALTER TABLE corporations DROP FOREIGN KEY FK_6F3B37C710A0EA3F');
        $this->addSql('ALTER TABLE characters DROP FOREIGN KEY FK_3A29410EB2685369');
        $this->addSql('ALTER TABLE corporation_group DROP FOREIGN KEY FK_E13C0842B2685369');
        $this->addSql('DROP TABLE alliances');
        $this->addSql('DROP TABLE corporations');
        $this->addSql('DROP TABLE corporation_group');
        $this->addSql('DROP INDEX IDX_3A29410EB2685369 ON characters');
        $this->addSql('DROP INDEX IDX_3A29410E10A0EA3F ON characters');
        $this->addSql('ALTER TABLE characters DROP corporation_id, DROP alliance_id');
    }
}
