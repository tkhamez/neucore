<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200801163459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');

        $this->addSql('ALTER TABLE watchlists CHANGE id id INT AUTO_INCREMENT NOT NULL');

        $this->addSql('ALTER TABLE watchlist_kicklist_corporation DROP FOREIGN KEY FK_8DDD9A7183DD0D94');
        $this->addSql('ALTER TABLE watchlist_kicklist_corporation DROP FOREIGN KEY FK_8DDD9A71B2685369');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance DROP FOREIGN KEY FK_71972D2C10A0EA3F');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance DROP FOREIGN KEY FK_71972D2C83DD0D94');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation DROP FOREIGN KEY FK_82FF578E83DD0D94');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation DROP FOREIGN KEY FK_82FF578EB2685369');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance DROP FOREIGN KEY FK_AF2D6D8610A0EA3F');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance DROP FOREIGN KEY FK_AF2D6D8683DD0D94');

        $this->addSql('ALTER TABLE watchlist_kicklist_corporation DROP INDEX idx_8ddd9a7183dd0d94');
        $this->addSql('ALTER TABLE watchlist_kicklist_corporation DROP INDEX idx_8ddd9a71b2685369');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance DROP INDEX idx_71972d2c83dd0d94');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance DROP INDEX idx_71972d2c10a0ea3f');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation DROP INDEX idx_82ff578e83dd0d94');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation DROP INDEX idx_82ff578eb2685369');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance DROP INDEX idx_af2d6d8683dd0d94');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance DROP INDEX idx_af2d6d8610a0ea3f');

        $this->addSql('CREATE INDEX IDX_ED38E20383DD0D94 ON watchlist_kicklist_corporation (watchlist_id)');
        $this->addSql('CREATE INDEX IDX_ED38E203B2685369 ON watchlist_kicklist_corporation (corporation_id)');
        $this->addSql('CREATE INDEX IDX_4956D23E83DD0D94 ON watchlist_kicklist_alliance (watchlist_id)');
        $this->addSql('CREATE INDEX IDX_4956D23E10A0EA3F ON watchlist_kicklist_alliance (alliance_id)');
        $this->addSql('CREATE INDEX IDX_5F58008B83DD0D94 ON watchlist_allowlist_corporation (watchlist_id)');
        $this->addSql('CREATE INDEX IDX_5F58008BB2685369 ON watchlist_allowlist_corporation (corporation_id)');
        $this->addSql('CREATE INDEX IDX_9BE9B6B283DD0D94 ON watchlist_allowlist_alliance (watchlist_id)');
        $this->addSql('CREATE INDEX IDX_9BE9B6B210A0EA3F ON watchlist_allowlist_alliance (alliance_id)');

        $this->addSql('ALTER TABLE watchlist_kicklist_corporation ADD CONSTRAINT FK_8DDD9A7183DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_kicklist_corporation ADD CONSTRAINT FK_8DDD9A71B2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance ADD CONSTRAINT FK_71972D2C10A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance ADD CONSTRAINT FK_71972D2C83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation ADD CONSTRAINT FK_82FF578E83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation ADD CONSTRAINT FK_82FF578EB2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance ADD CONSTRAINT FK_AF2D6D8610A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance ADD CONSTRAINT FK_AF2D6D8683DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');

        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');

        $this->addSql('ALTER TABLE watchlist_kicklist_corporation DROP FOREIGN KEY FK_8DDD9A7183DD0D94');
        $this->addSql('ALTER TABLE watchlist_kicklist_corporation DROP FOREIGN KEY FK_8DDD9A71B2685369');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance DROP FOREIGN KEY FK_71972D2C10A0EA3F');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance DROP FOREIGN KEY FK_71972D2C83DD0D94');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation DROP FOREIGN KEY FK_82FF578E83DD0D94');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation DROP FOREIGN KEY FK_82FF578EB2685369');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance DROP FOREIGN KEY FK_AF2D6D8610A0EA3F');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance DROP FOREIGN KEY FK_AF2D6D8683DD0D94');

        $this->addSql('ALTER TABLE watchlist_kicklist_corporation DROP INDEX IDX_ED38E20383DD0D94');
        $this->addSql('ALTER TABLE watchlist_kicklist_corporation DROP INDEX IDX_ED38E203B2685369');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance DROP INDEX IDX_4956D23E83DD0D94');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance DROP INDEX IDX_4956D23E10A0EA3F');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation DROP INDEX IDX_5F58008B83DD0D94');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation DROP INDEX IDX_5F58008BB2685369');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance DROP INDEX IDX_9BE9B6B283DD0D94');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance DROP INDEX IDX_9BE9B6B210A0EA3F');

        $this->addSql('CREATE INDEX idx_8ddd9a7183dd0d94 ON watchlist_kicklist_corporation (watchlist_id)');
        $this->addSql('CREATE INDEX idx_8ddd9a71b2685369 ON watchlist_kicklist_corporation (corporation_id)');
        $this->addSql('CREATE INDEX idx_71972d2c83dd0d94 ON watchlist_kicklist_alliance (watchlist_id)');
        $this->addSql('CREATE INDEX idx_71972d2c10a0ea3f ON watchlist_kicklist_alliance (alliance_id)');
        $this->addSql('CREATE INDEX idx_82ff578e83dd0d94 ON watchlist_allowlist_corporation (watchlist_id)');
        $this->addSql('CREATE INDEX idx_82ff578eb2685369 ON watchlist_allowlist_corporation (corporation_id)');
        $this->addSql('CREATE INDEX idx_af2d6d8683dd0d94 ON watchlist_allowlist_alliance (watchlist_id)');
        $this->addSql('CREATE INDEX idx_af2d6d8610a0ea3f ON watchlist_allowlist_alliance (alliance_id)');

        $this->addSql('ALTER TABLE watchlist_kicklist_corporation ADD CONSTRAINT FK_8DDD9A7183DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_kicklist_corporation ADD CONSTRAINT FK_8DDD9A71B2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance ADD CONSTRAINT FK_71972D2C10A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance ADD CONSTRAINT FK_71972D2C83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation ADD CONSTRAINT FK_82FF578E83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation ADD CONSTRAINT FK_82FF578EB2685369 FOREIGN KEY (corporation_id) REFERENCES corporations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance ADD CONSTRAINT FK_AF2D6D8610A0EA3F FOREIGN KEY (alliance_id) REFERENCES alliances (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance ADD CONSTRAINT FK_AF2D6D8683DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE watchlists CHANGE id id INT NOT NULL');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
