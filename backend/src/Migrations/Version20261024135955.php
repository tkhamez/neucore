<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261024135955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_access_group (app_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_46DB22177987212D (app_id), INDEX IDX_46DB2217FE54D947 (group_id), PRIMARY KEY(app_id, group_id))');
        $this->addSql('ALTER TABLE app_access_group ADD CONSTRAINT FK_46DB22177987212D FOREIGN KEY (app_id) REFERENCES apps (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_access_group ADD CONSTRAINT FK_46DB2217FE54D947 FOREIGN KEY (group_id) REFERENCES groups_tbl (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_access_group DROP FOREIGN KEY FK_46DB22177987212D');
        $this->addSql('ALTER TABLE app_access_group DROP FOREIGN KEY FK_46DB2217FE54D947');
        $this->addSql('DROP TABLE app_access_group');
    }
}
