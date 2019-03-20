<?php

declare(strict_types=1);

namespace Brave\Core\Migrations;

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
        $this->addSql('ALTER TABLE groups_tbl RENAME INDEX uniq_f06d39705e237e06 TO UNIQ_DF76BD475E237E06');
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE groups_tbl RENAME INDEX uniq_df76bd475e237e06 TO UNIQ_F06D39705E237E06');
        $this->addSql('ALTER TABLE groups_tbl RENAME `groups`');
    }
}
