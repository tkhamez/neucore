<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190831160551 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE esi_types (id BIGINT NOT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE corporation_members DROP ship_type_id');
        $this->addSql('ALTER TABLE corporation_members ADD shipType_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE corporation_members ADD CONSTRAINT FK_4E085D3457A0D84 FOREIGN KEY (shipType_id) REFERENCES esi_types (id)');
        $this->addSql('CREATE INDEX IDX_4E085D3457A0D84 ON corporation_members (shipType_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE corporation_members DROP FOREIGN KEY FK_4E085D3457A0D84');
        $this->addSql('DROP TABLE esi_types');
        $this->addSql('DROP INDEX IDX_4E085D3457A0D84 ON corporation_members');
        $this->addSql('ALTER TABLE corporation_members DROP shipType_id');
        $this->addSql('ALTER TABLE corporation_members ADD ship_type_id BIGINT DEFAULT NULL');
    }
}
