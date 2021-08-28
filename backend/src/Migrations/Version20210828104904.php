<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210828104904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE esi_tokens DROP FOREIGN KEY FK_1CCBCAB17E063B60');
        $this->addSql('ALTER TABLE esi_tokens ADD CONSTRAINT FK_1CCBCAB17E063B60 FOREIGN KEY (eve_login_id) REFERENCES eve_logins (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE esi_tokens DROP FOREIGN KEY FK_1CCBCAB17E063B60');
        $this->addSql('ALTER TABLE esi_tokens ADD CONSTRAINT FK_1CCBCAB17E063B60 FOREIGN KEY (eve_login_id) REFERENCES eve_logins (id)');
    }
}
