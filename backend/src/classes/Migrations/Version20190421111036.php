<?php

declare(strict_types=1);

namespace Brave\Core\Migrations;

use Brave\Core\Entity\RemovedCharacter;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190421111036 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    /**
     * @throws DBALException
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql(
            "UPDATE removed_characters SET reason = '".RemovedCharacter::REASON_DELETED_MANUALLY."' 
            WHERE reason = 'deleted (manually)'"
        );
        $this->addSql(
            "UPDATE removed_characters SET reason = '".RemovedCharacter::REASON_DELETED_BIOMASSED."' 
            WHERE reason = 'deleted (biomassed)'"
        );
        $this->addSql(
            "UPDATE removed_characters SET reason = '".RemovedCharacter::REASON_DELETED_OWNER_CHANGED."' 
            WHERE reason = 'deleted (EVE account changed)'"
        );

        $this->addSql('ALTER TABLE removed_characters CHANGE reason reason VARCHAR(32) NOT NULL');
    }

    /**
     * @throws DBALException
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE removed_characters CHANGE reason reason VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');

        $this->addSql(
            "UPDATE removed_characters SET reason = 'deleted (manually)' 
            WHERE reason = '".RemovedCharacter::REASON_DELETED_MANUALLY."'"
        );
        $this->addSql(
            "UPDATE removed_characters SET reason = 'deleted (biomassed)' 
            WHERE reason = '".RemovedCharacter::REASON_DELETED_BIOMASSED."'"
        );
        $this->addSql(
            "UPDATE removed_characters SET reason = 'deleted (EVE account changed)' 
            WHERE reason = '".RemovedCharacter::REASON_DELETED_OWNER_CHANGED."'"
        );
    }
}
