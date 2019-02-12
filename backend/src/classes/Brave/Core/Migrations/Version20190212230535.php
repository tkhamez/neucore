<?php declare(strict_types=1);

namespace Brave\Core\Migrations;

use Brave\Core\Entity\Role;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190212230535 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('INSERT INTO roles (id, name) VALUES (13, "'.Role::APP_GROUPS.'")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (14, "'.Role::APP_CHARS.'")');

        $this->addSql('INSERT INTO app_role (app_id, role_id) SELECT id, 13 FROM apps');
        $this->addSql('INSERT INTO app_role (app_id, role_id) SELECT id, 14 FROM apps');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DELETE FROM app_role WHERE role_id = 13');
        $this->addSql('DELETE FROM app_role WHERE role_id = 14');

        $this->addSql('DELETE FROM roles WHERE id = 13');
        $this->addSql('DELETE FROM roles WHERE id = 14');
    }
}
