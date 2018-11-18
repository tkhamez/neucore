<?php declare(strict_types=1);

namespace Brave\Core\Migrations;

use Brave\Core\Entity\Role;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180806193042 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('INSERT INTO roles (id, name) VALUES (8, "'.Role::ESI.'")');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
