<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181224140727 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('CREATE TABLE corporation_members (id BIGINT NOT NULL, corporation_id BIGINT DEFAULT NULL, character_id BIGINT DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, location_id BIGINT DEFAULT NULL, logoff_date DATETIME DEFAULT NULL, logon_date DATETIME DEFAULT NULL, ship_type_id BIGINT DEFAULT NULL, start_date DATETIME DEFAULT NULL, INDEX IDX_4E085D34B2685369 (corporation_id), UNIQUE INDEX UNIQ_4E085D341136BE75 (character_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE corporation_members ADD CONSTRAINT FK_4E085D34B2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id)');
        $this->addSql('ALTER TABLE corporation_members ADD CONSTRAINT FK_4E085D341136BE75 FOREIGN KEY (character_id) REFERENCES characters (id)');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('DROP TABLE corporation_members');
    }
}
