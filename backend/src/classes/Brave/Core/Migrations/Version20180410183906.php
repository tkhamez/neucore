<?php declare(strict_types = 1);

namespace Brave\Core\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180410183906 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql('INSERT INTO roles (id, name) VALUES (3, "user-admin")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (4, "group-admin")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (5, "group-manager")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (6, "app-admin")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (7, "app-manager")');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
