<?php

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180228212701 extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE characters ADD character_owner_hash TEXT NOT NULL, ADD access_token TEXT NOT NULL, ADD expires INT NOT NULL, ADD refresh_token TEXT NOT NULL');
    }

    /**
     * @throws \Exception
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE characters DROP character_owner_hash, DROP access_token, DROP expires, DROP refresh_token');
    }
}
