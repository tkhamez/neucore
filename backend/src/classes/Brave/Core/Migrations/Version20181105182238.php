<?php declare(strict_types=1);

namespace Brave\Core\Migrations;

use Brave\Core\Entity\Role;
use Brave\Core\Entity\SystemVariable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181105182238 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE settings (name VARCHAR(255) NOT NULL, value TEXT DEFAULT NULL, PRIMARY KEY(name)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');

        $this->addSql('INSERT INTO roles (id, name) VALUES (9, "'.Role::SETTINGS.'")');
        $this->addSql('INSERT INTO settings (name, value) VALUES ("'.SystemVariable::ALLOW_CHARACTER_DELETION.'", "0")');
        $this->addSql('INSERT INTO settings (name, value) VALUES ("'.SystemVariable::GROUPS_REQUIRE_VALID_TOKEN.'", "0")');
        $this->addSql('INSERT INTO settings (name, value) VALUES ("'.SystemVariable::SHOW_PREVIEW_BANNER.'", "0")');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE settings');
        $this->addSql('DELETE FROM roles WHERE id = 9');
    }
}
