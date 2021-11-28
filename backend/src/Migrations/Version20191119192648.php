<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191119192648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('CREATE TABLE watchlists (id INT NOT NULL, name VARCHAR(32) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE watchlist_player (watchlist_id INT NOT NULL, player_id INT NOT NULL, INDEX IDX_310B07AD83DD0D94 (watchlist_id), INDEX IDX_310B07AD99E6F5DF (player_id), PRIMARY KEY(watchlist_id, player_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE watchlist_corporation (watchlist_id INT NOT NULL, corporation_id BIGINT NOT NULL, INDEX IDX_A47DFD6683DD0D94 (watchlist_id), INDEX IDX_A47DFD66B2685369 (corporation_id), PRIMARY KEY(watchlist_id, corporation_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE watchlist_alliance (watchlist_id INT NOT NULL, alliance_id BIGINT NOT NULL, INDEX IDX_412DA1EA83DD0D94 (watchlist_id), INDEX IDX_412DA1EA10A0EA3F (alliance_id), PRIMARY KEY(watchlist_id, alliance_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE watchlist_group (watchlist_id INT NOT NULL, group_id INT NOT NULL, INDEX IDX_C313DF2983DD0D94 (watchlist_id), INDEX IDX_C313DF29FE54D947 (group_id), PRIMARY KEY(watchlist_id, group_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE watchlist_player ADD CONSTRAINT FK_310B07AD83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_player ADD CONSTRAINT FK_310B07AD99E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_corporation ADD CONSTRAINT FK_A47DFD6683DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_corporation ADD CONSTRAINT FK_A47DFD66B2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_alliance ADD CONSTRAINT FK_412DA1EA83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_alliance ADD CONSTRAINT FK_412DA1EA10A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_group ADD CONSTRAINT FK_C313DF2983DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_group ADD CONSTRAINT FK_C313DF29FE54D947 FOREIGN KEY (group_id) REFERENCES groups_tbl (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE watchlist_player DROP FOREIGN KEY FK_310B07AD83DD0D94');
        $this->addSql('ALTER TABLE watchlist_corporation DROP FOREIGN KEY FK_A47DFD6683DD0D94');
        $this->addSql('ALTER TABLE watchlist_alliance DROP FOREIGN KEY FK_412DA1EA83DD0D94');
        $this->addSql('ALTER TABLE watchlist_group DROP FOREIGN KEY FK_C313DF2983DD0D94');
        $this->addSql('DROP TABLE watchlists');
        $this->addSql('DROP TABLE watchlist_player');
        $this->addSql('DROP TABLE watchlist_corporation');
        $this->addSql('DROP TABLE watchlist_alliance');
        $this->addSql('DROP TABLE watchlist_group');
    }
}
