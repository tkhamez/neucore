<?php

declare(strict_types = 1);
namespace Brave\Core\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180416212822 extends AbstractMigration
{
    public function up(Schema $schema)
    {
        $this->addSql('DELETE FROM apps WHERE id = :id', array('id' => 1));
    }

    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
