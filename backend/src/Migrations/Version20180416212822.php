<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180416212822 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // Previously an app was added in Version20171229114138
        $this->addSql('DELETE FROM apps WHERE id = :id', ['id' => 1]);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
