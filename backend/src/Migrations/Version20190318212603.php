<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190318212603 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        #$this->addSql("UPDATE players SET name = CONCAT(name, '#', id) WHERE name NOT LIKE '%#%'");
    }

    public function down(Schema $schema): void
    {
        // There's nothing to do here.
    }
}
