<?php declare(strict_types=1);

namespace Brave\Core\Migrations;

use Brave\Core\Entity\SystemVariable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181117203300 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE settings RENAME system_variables');

        $this->addSql('ALTER TABLE system_variables ADD scope VARCHAR(16) DEFAULT \'public\' NOT NULL');
        $this->addSql('INSERT INTO system_variables (name, value, scope) VALUES ("'.SystemVariable::MAIL_CHARACTER.'", "", "settings")');
        $this->addSql('INSERT INTO system_variables (name, value, scope) VALUES ("'.SystemVariable::MAIL_TOKEN.'", "", "backend")');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE system_variables DROP scope');

        $this->addSql('DELETE FROM system_variables WHERE name = "'.SystemVariable::MAIL_CHARACTER.'"');
        $this->addSql('DELETE FROM system_variables WHERE name = "'.SystemVariable::MAIL_TOKEN.'"');

        $this->addSql('ALTER TABLE system_variables RENAME settings');
    }
}
