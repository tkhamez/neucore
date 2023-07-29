<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230729134003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sessions CHANGE sess_lifetime sess_lifetime INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE sessions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sessions CHANGE sess_lifetime sess_lifetime INT NOT NULL');
        $this->addSql('ALTER TABLE sessions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
    }
}
