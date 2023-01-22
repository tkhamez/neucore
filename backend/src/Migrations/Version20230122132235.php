<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230122132235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE alliance_group CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE alliances CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE app_eve_login CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE app_group CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE app_manager CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE app_requests CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE app_role CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE apps CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE character_name_changes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE characters CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE corporation_group CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql('ALTER TABLE corporation_group_tracking CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE corporation_members CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE corporations CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE doctrine_migration_versions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE esi_locations CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE esi_tokens CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE esi_types CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE eve_logins CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE group_applications CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE group_forbidden_groups CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE group_manager CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE group_required_groups CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE groups_tbl CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE player_group CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE player_logins CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE player_role CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE players CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE plugins CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE removed_characters CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE role_required_group CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE roles CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE sessions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE system_variables CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE watchlist_alliance CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE watchlist_allowlist_alliance CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE watchlist_allowlist_corporation CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE watchlist_corporation CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE watchlist_exemption CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE watchlist_group CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE watchlist_kicklist_alliance CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE watchlist_kicklist_corporation CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE watchlist_manager_group CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');
        $this->addSql(' ALTER TABLE watchlists CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci');

        $this->addSql('ALTER TABLE characters CHANGE character_owner_hash character_owner_hash TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE esi_tokens CHANGE refresh_token refresh_token TEXT NOT NULL, CHANGE access_token access_token TEXT NOT NULL');
        $this->addSql('ALTER TABLE plugins CHANGE configuration configuration MEDIUMTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE system_variables CHANGE variable_value variable_value MEDIUMTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE alliance_group CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE alliances CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE app_eve_login CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE app_group CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE app_manager CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE app_requests CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE app_role CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE apps CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE character_name_changes CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE characters CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE corporation_group CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE corporation_group_tracking CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE corporation_members CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE corporations CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE doctrine_migration_versions CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE esi_locations CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE esi_tokens CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE esi_types CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE eve_logins CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE group_applications CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE group_forbidden_groups CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE group_manager CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE group_required_groups CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE groups_tbl CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE player_group CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE player_logins CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE player_role CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE players CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE plugins CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE removed_characters CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE role_required_group CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE roles CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE sessions CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE system_variables CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE watchlist_alliance CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE watchlist_allowlist_alliance CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE watchlist_allowlist_corporation CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE watchlist_corporation CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE watchlist_exemption CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE watchlist_group CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE watchlist_kicklist_alliance CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE watchlist_kicklist_corporation CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE watchlist_manager_group CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        $this->addSql(' ALTER TABLE watchlists CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
    }
}
