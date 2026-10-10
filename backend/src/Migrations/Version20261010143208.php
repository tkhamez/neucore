<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261010143208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_corporation (app_id INT NOT NULL, corporation_id BIGINT NOT NULL, INDEX IDX_DDCF703F7987212D (app_id), INDEX IDX_DDCF703FB2685369 (corporation_id), PRIMARY KEY(app_id, corporation_id))');
        $this->addSql('ALTER TABLE app_corporation ADD CONSTRAINT FK_DDCF703F7987212D FOREIGN KEY (app_id) REFERENCES apps (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_corporation ADD CONSTRAINT FK_DDCF703FB2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_corporation DROP FOREIGN KEY FK_DDCF703F7987212D');
        $this->addSql('ALTER TABLE app_corporation DROP FOREIGN KEY FK_DDCF703FB2685369');
        $this->addSql('DROP TABLE app_corporation');
    }
}
