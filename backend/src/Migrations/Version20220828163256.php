<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Neucore\Entity\EveLogin;

final class Version20220828163256 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // This cannot be a seed because the ID is needed in the next migration file

        $this->addSql(
            'INSERT INTO eve_logins (name, description, esi_scopes, eve_roles) 
            VALUES (:name, :description, :scopes, :roles)',
            [
                'name' => EveLogin::NAME_TRACKING,
                'description' => 'Token to get the member tracking data from ESI.',
                'scopes' => EveLogin::SCOPE_ROLES . ' ' . EveLogin::SCOPE_TRACKING . ' ' . EveLogin::SCOPE_STRUCTURES,
                'roles' => EveLogin::ROLE_DIRECTOR
            ]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM eve_logins WHERE name = :name', ['name' => EveLogin::NAME_TRACKING]);
    }
}
