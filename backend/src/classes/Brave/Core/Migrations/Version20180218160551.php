<?php declare(strict_types = 1);

namespace Brave\Core\Migrations;

use Brave\Core\Entity\Role;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180218160551 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE roles SET name = "'.Role::USER.'" WHERE id = 1');
        $this->addSql('UPDATE roles SET name = "'.Role::APP.'" WHERE id = 2');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE roles SET name = "role.user" WHERE id = 1');
        $this->addSql('UPDATE roles SET name = "role.app" WHERE id = 2');
    }
}
