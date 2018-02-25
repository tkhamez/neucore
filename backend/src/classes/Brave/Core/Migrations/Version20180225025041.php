<?php declare(strict_types = 1);

namespace Brave\Core\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180225025041 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE group_manager (group_id INT NOT NULL, player_id INT NOT NULL, INDEX IDX_7C2471B5FE54D947 (group_id), INDEX IDX_7C2471B599E6F5DF (player_id), PRIMARY KEY(group_id, player_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_manager (app_id INT NOT NULL, player_id INT NOT NULL, INDEX IDX_1779A5F47987212D (app_id), INDEX IDX_1779A5F499E6F5DF (player_id), PRIMARY KEY(app_id, player_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE group_manager ADD CONSTRAINT FK_7C2471B5FE54D947 FOREIGN KEY (group_id) REFERENCES groups (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_manager ADD CONSTRAINT FK_7C2471B599E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_manager ADD CONSTRAINT FK_1779A5F47987212D FOREIGN KEY (app_id) REFERENCES apps (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_manager ADD CONSTRAINT FK_1779A5F499E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE group_manager');
        $this->addSql('DROP TABLE app_manager');
    }
}
