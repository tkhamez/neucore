<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230121180812 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_requests DROP FOREIGN KEY FK_7305B6FC7987212D');
        $this->addSql('ALTER TABLE app_requests ADD CONSTRAINT FK_7305B6FC7987212D FOREIGN KEY (app_id) REFERENCES apps (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_requests DROP FOREIGN KEY FK_7305B6FC7987212D');
        $this->addSql('ALTER TABLE app_requests ADD CONSTRAINT FK_7305B6FC7987212D FOREIGN KEY (app_id) REFERENCES apps (id)');
    }
}
