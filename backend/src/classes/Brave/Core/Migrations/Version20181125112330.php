<?php declare(strict_types=1);

namespace Brave\Core\Migrations;

use Brave\Core\Entity\SystemVariable;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181125112330 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE characters ADD valid_token_time DATETIME DEFAULT NULL');
        $this->addSql('INSERT INTO system_variables (name, value, scope) VALUES ("'.SystemVariable::ACCOUNT_DEACTIVATION_DELAY.'", "", "settings")');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE characters DROP valid_token_time');
        $this->addSql('DELETE FROM system_variables WHERE name = "'.SystemVariable::ACCOUNT_DEACTIVATION_DELAY.'"');
    }
}
