<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Neucore\Entity\EveLogin;

final class Version20220828164937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $eveLoginId = $this->getEveLogin();
        $this->abortIf(!$eveLoginId, 'Error: EVE login ' . EveLogin::NAME_TRACKING . ' not found.');

        $directorSettings = $this->connection->executeQuery(
            'SELECT name, variable_value, scope FROM system_variables WHERE name LIKE ? OR name LIKE ?',
            ['director_char_%', 'director_token_%']
        );
        $data = [];
        foreach ($directorSettings->fetchAllAssociative() as $setting) {
            $number = (int)substr($setting['name'], strrpos($setting['name'], '_') + 1);
            $value = json_decode($setting['variable_value']);
            if (strpos($setting['name'], 'director_char_') === 0) {
                $data[$number]['characterId'] = $value->character_id;
            } elseif (strpos($setting['name'], 'director_token_') === 0) {
                $data[$number]['access'] = (string)$value->access;
                $data[$number]['refresh'] = (string)$value->refresh;
                $data[$number]['expires'] = $value->expires ?? time();
            }
        }

        foreach ($data as $token) {
            $character = $this->connection->executeQuery(
                'SELECT id FROM characters WHERE id = ?',
                [$token['characterId']]
            )->fetchAssociative();
            if (!isset($character['id'])) {
                continue;
            }
            $this->addSql(
                'INSERT INTO esi_tokens (character_id, eve_login_id, refresh_token, access_token, expires, 
                                         valid_token, valid_token_time) 
                VALUES (:characterId, :eveLoginId, :refresh, :access, :expires, :valid, :validTime)',
                [
                    'characterId' => $token['characterId'],
                    'eveLoginId' => $eveLoginId,
                    'refresh' => $token['refresh'],
                    'access' => $token['access'],
                    'expires' => $token['expires'],
                    'valid' => empty($token['refresh']) ? null : 1, // assume it is valid, or it will not be checked again
                    'validTime' => date('Y-m-d H:i:s'),
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $eveLoginId = $this->getEveLogin();
        $this->abortIf(!$eveLoginId, 'Error: EVE login ' . EveLogin::NAME_TRACKING . ' not found.');

        $this->addSql('DELETE FROM esi_tokens WHERE eve_login_id = ?', [$eveLoginId]);
    }

    /**
     * @throws Exception
     */
    private function getEveLogin()
    {
        $eveLogin = $this->connection->executeQuery(
            'SELECT id FROM eve_logins WHERE name = ?',
            [EveLogin::NAME_TRACKING]
        )->fetchAssociative();
        return $eveLogin['id'] ?? null;
    }
}
