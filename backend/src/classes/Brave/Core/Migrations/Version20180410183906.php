<?php declare(strict_types = 1);

namespace Brave\Core\Migrations;

use Brave\Core\Entity\Role;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180410183906 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql('INSERT INTO roles (id, name) VALUES (3, "'.Role::USER_ADMIN.'")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (4, "'.Role::GROUP_ADMIN.'")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (5, "'.Role::GROUP_MANAGER.'")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (6, "'.Role::APP_ADMIN.'")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (7, "'.Role::APP_MANAGER.'")');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
