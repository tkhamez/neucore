<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190320175003 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `groups` RENAME groups_tbl');
        $this->addSql('DROP INDEX uniq_f06d39705e237e06 ON groups_tbl');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DF76BD475E237E06 ON groups_tbl (name)');
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE groups_tbl RENAME `groups`');
        $this->addSql('DROP INDEX UNIQ_DF76BD475E237E06 ON `groups`');
        $this->addSql('CREATE UNIQUE INDEX uniq_f06d39705e237e06 ON `groups` (name)');
    }
}
