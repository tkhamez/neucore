<?php declare(strict_types = 1);

namespace Brave\Core\Migrations;

use Brave\Core\Roles;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180410183906 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql('INSERT INTO roles (id, name) VALUES (3, "'.Roles::USER_ADMIN.'")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (4, "'.Roles::GROUP_ADMIN.'")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (5, "'.Roles::GROUP_MANAGER.'")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (6, "'.Roles::APP_ADMIN.'")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (7, "'.Roles::APP_MANAGER.'")');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
