<?php declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180526190657 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE alliance_group (alliance_id BIGINT NOT NULL, group_id INT NOT NULL, INDEX IDX_29B5059110A0EA3F (alliance_id), INDEX IDX_29B50591FE54D947 (group_id), PRIMARY KEY(alliance_id, group_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE alliance_group ADD CONSTRAINT FK_29B5059110A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE alliance_group ADD CONSTRAINT FK_29B50591FE54D947 FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE CASCADE');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE alliance_group');
    }
}
