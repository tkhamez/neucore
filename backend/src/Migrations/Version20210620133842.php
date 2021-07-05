<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Neucore\Entity\EveLogin;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210620133842 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create EveLogin and EsiToken tables and copy ESI tokens from Character to EsiToken.';
    }

    public function up(Schema $schema): void
    {
        // Create new tables.
        $this->addSql(
            'CREATE TABLE esi_tokens (
                id INT AUTO_INCREMENT NOT NULL, character_id BIGINT NOT NULL, eve_login_id VARCHAR(20) NOT NULL, 
                refresh_token TEXT NOT NULL, access_token TEXT NOT NULL, expires INT NOT NULL, 
                INDEX IDX_1CCBCAB11136BE75 (character_id), 
                INDEX IDX_1CCBCAB17E063B60 (eve_login_id), 
                UNIQUE INDEX character_eve_login_idx (character_id, eve_login_id), 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE eve_logins (
                id VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(1024) NOT NULL, 
                esi_scopes VARCHAR(8192) NOT NULL, eve_roles VARCHAR(1024) NOT NULL, 
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB'
        );
        $this->addSql(
            'ALTER TABLE esi_tokens ADD CONSTRAINT FK_1CCBCAB11136BE75 FOREIGN KEY (character_id) 
            REFERENCES characters (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE esi_tokens ADD CONSTRAINT FK_1CCBCAB17E063B60 FOREIGN KEY (eve_login_id) 
            REFERENCES eve_logins (id)'
        );

        // Add default EveLogin and copy character tokens
        $loginId = EveLogin::ID_DEFAULT;
        $this->addSql(
            "INSERT INTO eve_logins (id, name, description, esi_scopes, eve_roles) 
            VALUES ('$loginId', 'Default Login', '', '', '')"
        );
        $this->addSql(
            "INSERT INTO esi_tokens (character_id, eve_login_id, refresh_token, access_token, expires) 
            SELECT id, '$loginId', refresh_token, access_token, expires
            FROM characters WHERE refresh_token IS NOT NULL AND refresh_token <> ''"
        );

        // Drop columns
        $this->addSql('ALTER TABLE characters DROP access_token, DROP expires, DROP refresh_token');
    }

    public function down(Schema $schema): void
    {
        // Create columns
        $this->addSql(
            'ALTER TABLE characters 
            ADD access_token TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, 
            ADD expires INT DEFAULT NULL,
            ADD refresh_token TEXT CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`'
        );

        // Copy data
        $loginId = EveLogin::ID_DEFAULT;
        $this->addSql(
            "UPDATE characters SET access_token = 
            (SELECT access_token FROM esi_tokens WHERE character_id = characters.id AND eve_login_id = '$loginId');"
        );
        $this->addSql(
            "UPDATE characters SET expires = 
            (SELECT expires FROM esi_tokens WHERE character_id = characters.id AND eve_login_id = '$loginId');"
        );
        $this->addSql(
            "UPDATE characters SET refresh_token = 
            (SELECT refresh_token FROM esi_tokens WHERE character_id = characters.id AND eve_login_id = '$loginId');"
        );

        // Delete tables
        $this->addSql('ALTER TABLE esi_tokens DROP FOREIGN KEY FK_1CCBCAB17E063B60');
        $this->addSql('DROP TABLE esi_tokens');
        $this->addSql('DROP TABLE eve_logins');
    }
}
