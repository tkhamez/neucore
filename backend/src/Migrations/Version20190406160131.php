<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190406160131 extends AbstractMigration
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

        $this->addSql('CREATE TABLE group_applications (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, group_id INT NOT NULL, created DATETIME DEFAULT NULL, INDEX IDX_1B8F2CC999E6F5DF (player_id), INDEX IDX_1B8F2CC9FE54D947 (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE group_applications ADD CONSTRAINT FK_1B8F2CC999E6F5DF FOREIGN KEY (player_id) REFERENCES players (id)');
        $this->addSql('ALTER TABLE group_applications ADD CONSTRAINT FK_1B8F2CC9FE54D947 FOREIGN KEY (group_id) REFERENCES groups_tbl (id)');
        $this->addSql('DROP TABLE group_applicant');
    }

    /**
     * @throws Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('CREATE TABLE group_applicant (player_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_A1E4CF8FE54D947 (group_id), INDEX IDX_A1E4CF899E6F5DF (player_id), PRIMARY KEY(player_id, group_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE group_applicant ADD CONSTRAINT FK_A1E4CF899E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_applicant ADD CONSTRAINT FK_A1E4CF8FE54D947 FOREIGN KEY (group_id) REFERENCES groups_tbl (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('DROP TABLE group_applications');
    }
}
