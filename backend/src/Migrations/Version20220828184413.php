<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Neucore\Entity\EveLogin;
use Neucore\Entity\SystemVariable;

final class Version20220828184413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'DELETE FROM system_variables WHERE name LIKE ? OR name LIKE ?',
            ['director_char_%', 'director_token_%']
        );
    }

    public function down(Schema $schema): void
    {
        $eveLogin = $this->connection->executeQuery(
            'SELECT id FROM eve_logins WHERE name = ?',
            [EveLogin::NAME_TRACKING]
        )->fetchAssociative();
        $this->abortIf(!isset($eveLogin['id']), 'Error: EVE login ' . EveLogin::NAME_TRACKING . ' not found.');

        $directorTokens = $this->connection->executeQuery(
            'SELECT e.character_id, e.refresh_token, e.access_token, e.expires, 
                    c.name AS char_name, c2.id AS corp_id, c2.name AS corp_name, c2.ticker 
            FROM esi_tokens e
            LEFT JOIN characters c on e.character_id = c.id
            LEFT JOIN corporations c2 on c.corporation_id = c2.id
            WHERE eve_login_id = ?',
            [$eveLogin['id']]
        )->fetchAllAssociative();

        foreach ($directorTokens as $idx => $tokenData) {
            $number = $idx + 1;
            $this->addSql(
                'INSERT INTO system_variables (name, variable_value, scope) VALUES (:name, :value, :scope)',
                [
                    'name' => "director_char_$number",
                    'value' => json_encode([
                        'character_id' => $tokenData['character_id'],
                        'character_name' => $tokenData['char_name'],
                        'corporation_id' => $tokenData['corp_id'],
                        'corporation_name' => $tokenData['corp_name'],
                        'corporation_ticker' => $tokenData['ticker'],
                    ]),
                    'scope' => 'settings'
                ]
            );
            $this->addSql(
                'INSERT INTO system_variables (name, variable_value, scope) VALUES (:name, :value, :scope)',
                [
                    'name' => "director_token_$number",
                    'value' => json_encode([
                        'access' => empty($tokenData['access_token']) ? null : $tokenData['access_token'],
                        'refresh' => empty($tokenData['refresh_token']) ? null : $tokenData['refresh_token'],
                        'expires' => $tokenData['expires'],
                        'scopes' => [
                            'esi-characters.read_corporation_roles.v1',
                            'esi-corporations.track_members.v1',
                            'esi-universe.read_structures.v1'
                        ],
                        'characterId' => null,
                        'systemVariableName' => null,
                    ]),
                    'scope' => 'backend'
                ]
            );
        }
    }
}
