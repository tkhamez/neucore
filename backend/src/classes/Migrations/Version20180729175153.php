<?php declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180729175153 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE alliances ADD last_update DATETIME DEFAULT NULL, CHANGE name name VARCHAR(255) DEFAULT NULL, CHANGE ticker ticker VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE corporations ADD last_update DATETIME DEFAULT NULL, CHANGE name name VARCHAR(255) DEFAULT NULL, CHANGE ticker ticker VARCHAR(16) DEFAULT NULL');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE alliances DROP last_update, CHANGE name name VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE ticker ticker VARCHAR(16) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE corporations DROP last_update, CHANGE name name VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE ticker ticker VARCHAR(16) NOT NULL COLLATE utf8_unicode_ci');
    }
}
