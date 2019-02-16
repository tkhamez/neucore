<?php declare(strict_types=1);

namespace Brave\Core\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180508174954 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE characters CHANGE character_owner_hash character_owner_hash TEXT DEFAULT NULL, CHANGE access_token access_token TEXT DEFAULT NULL');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE characters CHANGE character_owner_hash character_owner_hash TEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE access_token access_token TEXT NOT NULL COLLATE utf8_unicode_ci');
    }
}
