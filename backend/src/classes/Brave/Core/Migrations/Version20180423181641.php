<?php declare(strict_types = 1);

namespace Brave\Core\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180423181641 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE characters DROP FOREIGN KEY FK_3A29410E10A0EA3F');
        $this->addSql('DROP INDEX IDX_3A29410E10A0EA3F ON characters');
        $this->addSql('ALTER TABLE characters DROP alliance_id');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE characters ADD alliance_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE characters ADD CONSTRAINT FK_3A29410E10A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id)');
        $this->addSql('CREATE INDEX IDX_3A29410E10A0EA3F ON characters (alliance_id)');
    }
}
