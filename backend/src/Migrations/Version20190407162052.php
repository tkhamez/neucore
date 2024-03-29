<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190407162052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    /**
     * @throws \Exception
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE roles CHANGE id id INT(11) NOT NULL');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE roles CHANGE id id INT(11) AUTO_INCREMENT NOT NULL');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
