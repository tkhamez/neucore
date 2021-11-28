<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191220122206 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        // rename watchlist_player -> watchlist_exemption
        $this->addSql('ALTER TABLE watchlist_player DROP FOREIGN KEY FK_310B07AD83DD0D94');
        $this->addSql('ALTER TABLE watchlist_player DROP FOREIGN KEY FK_310B07AD99E6F5DF');
        $this->addSql('DROP INDEX IDX_310B07AD83DD0D94 ON watchlist_player');
        $this->addSql('DROP INDEX IDX_310B07AD99E6F5DF ON watchlist_player');
        $this->addSql('ALTER TABLE watchlist_player RENAME watchlist_exemption');
        $this->addSql('CREATE INDEX IDX_6DE889883DD0D94 ON watchlist_exemption (watchlist_id)');
        $this->addSql('CREATE INDEX IDX_6DE889899E6F5DF ON watchlist_exemption (player_id)');
        $this->addSql('ALTER TABLE watchlist_exemption ADD CONSTRAINT FK_6DE889883DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_exemption ADD CONSTRAINT FK_6DE889899E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE watchlist_blacklist_corporation (watchlist_id INT NOT NULL, corporation_id BIGINT NOT NULL, INDEX IDX_8DDD9A7183DD0D94 (watchlist_id), INDEX IDX_8DDD9A71B2685369 (corporation_id), PRIMARY KEY(watchlist_id, corporation_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE watchlist_blacklist_alliance (watchlist_id INT NOT NULL, alliance_id BIGINT NOT NULL, INDEX IDX_71972D2C83DD0D94 (watchlist_id), INDEX IDX_71972D2C10A0EA3F (alliance_id), PRIMARY KEY(watchlist_id, alliance_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE watchlist_whitelist_corporation (watchlist_id INT NOT NULL, corporation_id BIGINT NOT NULL, INDEX IDX_82FF578E83DD0D94 (watchlist_id), INDEX IDX_82FF578EB2685369 (corporation_id), PRIMARY KEY(watchlist_id, corporation_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE watchlist_whitelist_alliance (watchlist_id INT NOT NULL, alliance_id BIGINT NOT NULL, INDEX IDX_AF2D6D8683DD0D94 (watchlist_id), INDEX IDX_AF2D6D8610A0EA3F (alliance_id), PRIMARY KEY(watchlist_id, alliance_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE watchlist_blacklist_corporation ADD CONSTRAINT FK_8DDD9A7183DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_blacklist_corporation ADD CONSTRAINT FK_8DDD9A71B2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_blacklist_alliance ADD CONSTRAINT FK_71972D2C83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_blacklist_alliance ADD CONSTRAINT FK_71972D2C10A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_whitelist_corporation ADD CONSTRAINT FK_82FF578E83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_whitelist_corporation ADD CONSTRAINT FK_82FF578EB2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_whitelist_alliance ADD CONSTRAINT FK_AF2D6D8683DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_whitelist_alliance ADD CONSTRAINT FK_AF2D6D8610A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        // rename watchlist_exemption -> watchlist_player
        $this->addSql('ALTER TABLE watchlist_exemption DROP FOREIGN KEY FK_6DE889883DD0D94');
        $this->addSql('ALTER TABLE watchlist_exemption DROP FOREIGN KEY FK_6DE889899E6F5DF');
        $this->addSql('DROP INDEX IDX_6DE889883DD0D94 ON watchlist_exemption');
        $this->addSql('DROP INDEX IDX_6DE889899E6F5DF ON watchlist_exemption');
        $this->addSql('ALTER TABLE watchlist_exemption RENAME watchlist_player');
        $this->addSql('CREATE INDEX IDX_310B07AD83DD0D94 ON watchlist_player (watchlist_id)');
        $this->addSql('CREATE INDEX IDX_310B07AD99E6F5DF ON watchlist_player (player_id)');
        $this->addSql('ALTER TABLE watchlist_player ADD CONSTRAINT FK_310B07AD83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_player ADD CONSTRAINT FK_310B07AD99E6F5DF FOREIGN KEY (player_id) REFERENCES players (id) ON DELETE CASCADE');

        $this->addSql('DROP TABLE watchlist_blacklist_corporation');
        $this->addSql('DROP TABLE watchlist_blacklist_alliance');
        $this->addSql('DROP TABLE watchlist_whitelist_corporation');
        $this->addSql('DROP TABLE watchlist_whitelist_alliance');
    }
}
