<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190901123637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('CREATE TABLE esi_locations (id BIGINT NOT NULL, category VARCHAR(16) NOT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE corporation_members DROP FOREIGN KEY FK_4E085D3457A0D84');
        $this->addSql('DROP INDEX IDX_4E085D3457A0D84 ON corporation_members');
        $this->addSql('ALTER TABLE corporation_members CHANGE shiptype_id esi_type_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE corporation_members ADD CONSTRAINT FK_4E085D34739C3075 FOREIGN KEY (esi_type_id) REFERENCES esi_types (id)');
        $this->addSql('CREATE INDEX IDX_4E085D34739C3075 ON corporation_members (esi_type_id)');

        $this->addSql('ALTER TABLE corporation_members ADD esi_location_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE corporation_members ADD CONSTRAINT FK_4E085D349A27EBB8 FOREIGN KEY (esi_location_id) REFERENCES esi_locations (id)');
        $this->addSql('CREATE INDEX IDX_4E085D349A27EBB8 ON corporation_members (esi_location_id)');

        $this->addSql('ALTER TABLE corporation_members DROP location_id');

        $this->addSql('ALTER TABLE esi_locations ADD owner_id INT DEFAULT NULL, ADD system_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE esi_locations DROP owner_id, DROP system_id');

        $this->addSql('ALTER TABLE corporation_members ADD location_id BIGINT DEFAULT NULL');

        $this->addSql('ALTER TABLE corporation_members DROP FOREIGN KEY FK_4E085D349A27EBB8');
        $this->addSql('DROP INDEX IDX_4E085D349A27EBB8 ON corporation_members');
        $this->addSql('ALTER TABLE corporation_members DROP esi_location_id');

        $this->addSql('DROP TABLE esi_locations');
        $this->addSql('ALTER TABLE corporation_members DROP FOREIGN KEY FK_4E085D34739C3075');
        $this->addSql('DROP INDEX IDX_4E085D34739C3075 ON corporation_members');
        $this->addSql('ALTER TABLE corporation_members CHANGE esi_type_id shipType_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE corporation_members ADD CONSTRAINT FK_4E085D3457A0D84 FOREIGN KEY (shipType_id) REFERENCES esi_types (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_4E085D3457A0D84 ON corporation_members (shipType_id)');
    }
}
