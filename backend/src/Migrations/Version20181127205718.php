<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181127205718 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE removed_characters ADD new_player_id INT DEFAULT NULL, ADD action VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE removed_characters ADD CONSTRAINT FK_129104F7AB79F0B0 FOREIGN KEY (new_player_id) REFERENCES players (id)');
        $this->addSql('CREATE INDEX IDX_129104F7AB79F0B0 ON removed_characters (new_player_id)');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE removed_characters DROP FOREIGN KEY FK_129104F7AB79F0B0');
        $this->addSql('DROP INDEX IDX_129104F7AB79F0B0 ON removed_characters');
        $this->addSql('ALTER TABLE removed_characters DROP new_player_id, DROP action');
    }
}
