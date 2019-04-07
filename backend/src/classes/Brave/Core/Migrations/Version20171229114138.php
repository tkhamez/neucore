<?php declare(strict_types = 1);

namespace Brave\Core\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171229114138 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE users_roles (user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_51498A8EA76ED395 (user_id), INDEX IDX_51498A8ED60322AC (role_id), PRIMARY KEY(user_id, role_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users_groups (user_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_FF8AB7E0A76ED395 (user_id), INDEX IDX_FF8AB7E0FE54D947 (group_id), PRIMARY KEY(user_id, group_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `groups` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(64) NOT NULL, UNIQUE INDEX UNIQ_F06D39705E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE apps (id INT AUTO_INCREMENT NOT NULL, secret VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE apps_roles (app_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_D118FC337987212D (app_id), INDEX IDX_D118FC33D60322AC (role_id), PRIMARY KEY(app_id, role_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE roles (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(64) NOT NULL, UNIQUE INDEX UNIQ_B63E2EC75E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE users_roles ADD CONSTRAINT FK_51498A8EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users_roles ADD CONSTRAINT FK_51498A8ED60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users_groups ADD CONSTRAINT FK_FF8AB7E0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users_groups ADD CONSTRAINT FK_FF8AB7E0FE54D947 FOREIGN KEY (group_id) REFERENCES `groups` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE apps_roles ADD CONSTRAINT FK_D118FC337987212D FOREIGN KEY (app_id) REFERENCES apps (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE apps_roles ADD CONSTRAINT FK_D118FC33D60322AC FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE users ADD character_id BIGINT NOT NULL, CHANGE name name VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E91136BE75 ON users (character_id)');

        $this->addSql('INSERT INTO roles (id, name) VALUES (1, "role.user")');
        $this->addSql('INSERT INTO roles (id, name) VALUES (2, "role.app")');

        $secret = password_hash("my awesome secret", PASSWORD_DEFAULT);
        $this->addSql('INSERT INTO apps (id, secret, name) VALUES (1, :secret, "Example App")', array('secret' => $secret));

        $this->addSql('INSERT INTO apps_roles (app_id, role_id) VALUES (1, 2)');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE users_groups DROP FOREIGN KEY FK_FF8AB7E0FE54D947');
        $this->addSql('ALTER TABLE apps_roles DROP FOREIGN KEY FK_D118FC337987212D');
        $this->addSql('ALTER TABLE users_roles DROP FOREIGN KEY FK_51498A8ED60322AC');
        $this->addSql('ALTER TABLE apps_roles DROP FOREIGN KEY FK_D118FC33D60322AC');
        $this->addSql('DROP TABLE users_roles');
        $this->addSql('DROP TABLE users_groups');
        $this->addSql('DROP TABLE `groups`');
        $this->addSql('DROP TABLE apps');
        $this->addSql('DROP TABLE apps_roles');
        $this->addSql('DROP TABLE roles');
        $this->addSql('DROP INDEX UNIQ_1483A5E91136BE75 ON users');
        $this->addSql('ALTER TABLE users DROP character_id, CHANGE name name VARCHAR(64) NOT NULL COLLATE utf8_unicode_ci');
    }
}
