<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210620133842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(
            'CREATE TABLE esi_tokens (
                id INT AUTO_INCREMENT NOT NULL, character_id BIGINT NOT NULL, eve_login_id VARCHAR(64) NOT NULL, 
                refresh_token TEXT NOT NULL, access_token TEXT NOT NULL, expires INT NOT NULL, 
                INDEX IDX_1CCBCAB11136BE75 (character_id), 
                INDEX IDX_1CCBCAB17E063B60 (eve_login_id), 
                UNIQUE INDEX character_eve_login_idx (character_id, eve_login_id), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE eve_logins (
                id VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(1024) NOT NULL, 
                esiScopes VARCHAR(8192) NOT NULL, eveRoles VARCHAR(1024) NOT NULL, 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB'
        );
        $this->addSql(
            'ALTER TABLE esi_tokens ADD CONSTRAINT FK_1CCBCAB11136BE75 FOREIGN KEY (character_id) 
            REFERENCES characters (id)'
        );
        $this->addSql(
            'ALTER TABLE esi_tokens ADD CONSTRAINT FK_1CCBCAB17E063B60 FOREIGN KEY (eve_login_id) 
            REFERENCES eve_logins (id)'
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE esi_tokens DROP FOREIGN KEY FK_1CCBCAB17E063B60');
        $this->addSql('DROP TABLE esi_tokens');
        $this->addSql('DROP TABLE eve_logins');
    }
}
