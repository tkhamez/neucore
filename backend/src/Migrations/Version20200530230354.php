<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200530230354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('CREATE TABLE watchlist_manager_group (watchlist_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_2612D96183DD0D94 (watchlist_id), INDEX IDX_2612D961FE54D947 (group_id), PRIMARY KEY(watchlist_id, group_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_520_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE watchlist_manager_group ADD CONSTRAINT FK_2612D96183DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_manager_group ADD CONSTRAINT FK_2612D961FE54D947 FOREIGN KEY (group_id) REFERENCES groups_tbl (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('DROP TABLE watchlist_manager_group');
    }
}
