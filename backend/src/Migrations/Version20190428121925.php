<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190428121925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    /**
     * @throws Exception
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE corporation_members CHANGE corporation_id corporation_id BIGINT NOT NULL');
        $this->addSql('ALTER TABLE removed_characters CHANGE player_id player_id INT NOT NULL');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('ALTER TABLE corporation_members CHANGE corporation_id corporation_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE removed_characters CHANGE player_id player_id INT DEFAULT NULL');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
