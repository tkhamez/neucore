<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201122205350 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_requests (id INT AUTO_INCREMENT NOT NULL, app_id INT NOT NULL, day VARCHAR(10) NOT NULL, count INT NOT NULL, INDEX IDX_7305B6FC7987212D (app_id), INDEX day_idx (day), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE app_requests ADD CONSTRAINT FK_7305B6FC7987212D FOREIGN KEY (app_id) REFERENCES apps (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE app_requests');
    }
}
