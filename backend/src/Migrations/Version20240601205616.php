<?php

/** @noinspection DuplicatedCode */
/** @noinspection SqlResolve */
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240601205616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE watchlist_manager_group DROP FOREIGN KEY FK_2612D96183DD0D94');
        $this->addSql('ALTER TABLE watchlist_alliance DROP FOREIGN KEY FK_412DA1EA83DD0D94');
        $this->addSql('ALTER TABLE watchlist_group DROP FOREIGN KEY FK_C313DF2983DD0D94');
        $this->addSql('ALTER TABLE watchlist_exemption DROP FOREIGN KEY FK_6DE889883DD0D94');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance DROP FOREIGN KEY FK_71972D2C83DD0D94');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation DROP FOREIGN KEY FK_82FF578E83DD0D94');
        $this->addSql('ALTER TABLE watchlist_kicklist_corporation DROP FOREIGN KEY FK_8DDD9A7183DD0D94');
        $this->addSql('ALTER TABLE watchlist_corporation DROP FOREIGN KEY FK_A47DFD6683DD0D94');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance DROP FOREIGN KEY FK_AF2D6D8683DD0D94');

        // This is only missing for MariaDB 11.5 for some reason
        // (it's there for MariaDB 10.11, 11.4 and MySQL 8.0.22, 8.4.0)
        $this->addSql('ALTER TABLE watchlists CHANGE id id INT AUTO_INCREMENT NOT NULL');

        $this->addSql('ALTER TABLE watchlist_manager_group ADD CONSTRAINT FK_2612D96183DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_alliance ADD CONSTRAINT FK_412DA1EA83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_group ADD CONSTRAINT FK_C313DF2983DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_exemption ADD CONSTRAINT FK_6DE889883DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance ADD CONSTRAINT FK_71972D2C83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation ADD CONSTRAINT FK_82FF578E83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_kicklist_corporation ADD CONSTRAINT FK_8DDD9A7183DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_corporation ADD CONSTRAINT FK_A47DFD6683DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance ADD CONSTRAINT FK_AF2D6D8683DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE watchlist_manager_group DROP FOREIGN KEY FK_2612D96183DD0D94');
        $this->addSql('ALTER TABLE watchlist_alliance DROP FOREIGN KEY FK_412DA1EA83DD0D94');
        $this->addSql('ALTER TABLE watchlist_group DROP FOREIGN KEY FK_C313DF2983DD0D94');
        $this->addSql('ALTER TABLE watchlist_exemption DROP FOREIGN KEY FK_6DE889883DD0D94');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance DROP FOREIGN KEY FK_71972D2C83DD0D94');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation DROP FOREIGN KEY FK_82FF578E83DD0D94');
        $this->addSql('ALTER TABLE watchlist_kicklist_corporation DROP FOREIGN KEY FK_8DDD9A7183DD0D94');
        $this->addSql('ALTER TABLE watchlist_corporation DROP FOREIGN KEY FK_A47DFD6683DD0D94');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance DROP FOREIGN KEY FK_AF2D6D8683DD0D94');

        $this->addSql('ALTER TABLE watchlists CHANGE id id INT NOT NULL');

        $this->addSql('ALTER TABLE watchlist_manager_group ADD CONSTRAINT FK_2612D96183DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_alliance ADD CONSTRAINT FK_412DA1EA83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_group ADD CONSTRAINT FK_C313DF2983DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_exemption ADD CONSTRAINT FK_6DE889883DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_kicklist_alliance ADD CONSTRAINT FK_71972D2C83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_corporation ADD CONSTRAINT FK_82FF578E83DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_kicklist_corporation ADD CONSTRAINT FK_8DDD9A7183DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_corporation ADD CONSTRAINT FK_A47DFD6683DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE watchlist_allowlist_alliance ADD CONSTRAINT FK_AF2D6D8683DD0D94 FOREIGN KEY (watchlist_id) REFERENCES watchlists (id) ON DELETE CASCADE');
    }
}
