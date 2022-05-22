<?php

declare(strict_types=1);

namespace Neucore\Service;

use Eve\Sso\EveAuthentication;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\PlayerLogins;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Psr\Log\LoggerInterface;

class Account
{
    /**
     * Result for checkCharacter() if token is valid.
     */
    public const CHECK_TOKEN_OK = 1;

    /**
     * Result for checkCharacter() if token is invalid.
     */
    public const CHECK_TOKEN_NOK = 2;

    /**
     * Result for checkCharacter() if the character was deleted.
     */
    public const CHECK_CHAR_DELETED = 3;

    /**
     * Result for checkCharacter() if token could not be parsed.
     */
    public const CHECK_TOKEN_PARSE_ERROR = 4;

    /**
     * Result for checkCharacter() if there is no refresh token.
     */
    public const CHECK_TOKEN_NA = 5;

    private LoggerInterface $log;

    private RepositoryFactory $repositoryFactory;

    private ObjectManager $objectManager;

    private EsiData $esiData;

    private AutoGroupAssignment $autoGroupAssignment;

    private OAuthToken $tokenService;

    public function __construct(
        LoggerInterface $log,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        EsiData $esiData,
        AutoGroupAssignment $autoGroupAssignment,
        OAuthToken $tokenService
    ) {
        $this->log = $log;
        $this->objectManager = $objectManager;
        $this->repositoryFactory = $repositoryFactory;
        $this->esiData = $esiData;
        $this->autoGroupAssignment = $autoGroupAssignment;
        $this->tokenService = $tokenService;
    }

    /**
     * Creates Player and Character objects.
     *
     * Does not persist them in the database.
     */
    public function createNewPlayerWithMain(int $characterId, string $characterName): Character
    {
        $player = new Player();
        $player->setName($characterName);

        $char = new Character();
        $char->setId($characterId);
        $char->setName($characterName);
        $char->setMain(true);
        try {
            $char->setCreated(new \DateTime());
        } catch (\Exception $e) {
            // ignore
        }
        $char->setPlayer($player);
        $player->addCharacter($char);

        return $char;
    }

    /**
     * Moves character to a new player because the character owner hash changed.
     *
     * Does not flush the entity manager.
     */
    public function moveCharacterToNewAccount(Character $char): Character
    {
        $newPlayer = new Player();
        $newPlayer->setName($char->getName());
        $this->objectManager->persist($newPlayer);

        $this->moveCharacter($char, $newPlayer, RemovedCharacter::REASON_MOVED_OWNER_CHANGED);

        $char->setMain(true);

        return $char;
    }

    /**
     * Update and save character and player after a successful login.
     *
     * Updates character with the data provided and persists player
     * and character in the database. Both Entities can be new.
     *
     * Does *not* update the character name, see comment in code.
     *
     * @param Character $char Character with Player object attached.
     * @param EveAuthentication $eveAuth
     * @param bool $updateAutoGroups Update "auto groups" if the character is new or was moved to another account
     * @return bool
     */
    public function updateAndStoreCharacterWithPlayer(
        Character $char,
        EveAuthentication $eveAuth,
        bool $updateAutoGroups
    ): bool {
        // update character
        $token = $eveAuth->getToken();
        // Do not update the character name: after a character rename the name from SSO is/can be? the old name.
        // https://github.com/ccpgames/sso-issues/issues/68
        try {
            $char->setLastLogin(new \DateTime());
        } catch (\Exception $e) {
            // ignore
        }
        $char->setCharacterOwnerHash($eveAuth->getCharacterOwnerHash());

        // Get and update, create or remove default ESI token.
        $esiToken = $char->getEsiToken(EveLogin::NAME_DEFAULT);
        if ($esiToken === null) {
            $eveLogin = $this->repositoryFactory->getEveLoginRepository()
                ->findOneBy(['name' => EveLogin::NAME_DEFAULT]);
            if ($eveLogin === null) {
                $this->log->error(
                    'Account::updateAndStoreCharacterWithPlayer: Could not find default EveLogin entity.'
                );
                return false;
            }
            $esiToken = new EsiToken();
            $esiToken->setEveLogin($eveLogin);
            $esiToken->setCharacter($char);
            $char->addEsiToken($esiToken);
            $this->objectManager->persist($esiToken);
        }
        if (is_numeric($token->getExpires()) && is_string($token->getRefreshToken())) {
            $esiToken->setAccessToken($token->getToken());
            $esiToken->setExpires($token->getExpires());
            $esiToken->setRefreshToken($token->getRefreshToken());
        } else {
            $char->removeEsiToken($esiToken);
            $this->objectManager->remove($esiToken);
            $esiToken = null;
        }

        if ($esiToken !== null && !empty($this->tokenService->getScopesFromToken($esiToken))) {
            $esiToken->setValidToken(true);
        } elseif ($esiToken) {
            $esiToken->setValidToken(); // treat no scopes as if there was no token
        }

        // update account name
        if ($char->getMain()) {
            $char->getPlayer()->setName($char->getName());
        }

        // could be a new player and/or character, so persist
        $this->objectManager->persist($char->getPlayer());
        $this->objectManager->persist($char);

        $success = $this->objectManager->flush();

        // update character if corporation is missing
        if ($char->getCorporation() === null) {
            $this->esiData->fetchCharacterWithCorporationAndAlliance($char->getId());
        }

        // update groups
        if ($updateAutoGroups) {
            $this->updateGroups($char->getPlayer()->getId()); // flushes
        }

        return $success;
    }

    public function increaseLoginCount(Player $player): void
    {
        $year = (int) date('Y');
        $month = (int) date('m');

        $login = $this->repositoryFactory->getPlayerLoginsRepository()->findOneBy([
            'player' => $player,
            'year' => $year,
            'month' => $month,
        ]);
        if ($login === null) {
            $login = new PlayerLogins();
            $login->setPlayer($player);
            $login->setYear($year);
            $login->setMonth($month);
            $login->setCount(0);
            $this->objectManager->persist($login);
        }

        $login->setCount($login->getCount() + 1);

        $this->objectManager->flush();
    }

    /**
     * Checks if char was biomassed, the access/refresh tokens and the owner hash.
     *
     * The refresh tokens are verified by requesting a new access token
     * (If the access token is still valid the refresh token is not validated).
     *
     * The character is deleted if:
     * - the character owner hash changed
     * - the character was biomassed
     *
     * This updates the validToken property (true/false) and the token itself.
     *
     * The character is saved if it was changed.
     *
     * @param Character $char An instance that is attached to the Doctrine entity manager.
     * @return int One of the self::CHECK_* constants, based on the default token
     */
    public function checkCharacter(Character $char): int
    {
        // check if character is in Doomheim (biomassed)
        if (
            $char->getCorporation() !== null &&
            $char->getCorporation()->getId() === EsiData::CORPORATION_DOOMHEIM_ID
        ) {
            $this->deleteCharacter($char, RemovedCharacter::REASON_DELETED_BIOMASSED);
            $this->objectManager->flush();
            return self::CHECK_CHAR_DELETED;
        }

        // Update all non-default tokens and check required in-game roles
        foreach ($char->getEsiTokens() as $esiToken) {
            $eveLogin = $esiToken->getEveLogin();
            if ($eveLogin === null) { // should not happen
                continue;
            }
            if ($eveLogin->getName() === EveLogin::NAME_DEFAULT) {
                continue;
            }
            $token = $this->tokenService->updateEsiToken($esiToken);
            if (!$token) {
                continue; // can't check roles without a valid token
            }
            $oldHasRoles = $esiToken->getHasRoles();
            if (empty($eveLogin->getEveRoles())) {
                $esiToken->setHasRoles();
            } elseif ($this->esiData->verifyRoles(
                $eveLogin->getEveRoles(),
                $char->getId(),
                $esiToken->getAccessToken()
            )) {
                $esiToken->setHasRoles(true);
            } else {
                $esiToken->setHasRoles(false);
            }
            if ($oldHasRoles !== $esiToken->getHasRoles()) {
                $this->objectManager->flush();
            }
        }

        $defaultEsiToken = $char->getEsiToken(EveLogin::NAME_DEFAULT);

        // Does the char have a default token?
        if ($defaultEsiToken === null) {
            // Only true for SSOv1 without scopes or if the character was added directly to the database.
            return self::CHECK_TOKEN_NA;
        }

        // Validate default token - modifies $defaultEsiToken
        $token = $this->tokenService->updateEsiToken($defaultEsiToken);
        if ($token === null) {
            return self::CHECK_TOKEN_NOK;
        }

        // Get token data
        $eveAuth = $this->tokenService->getEveAuth($token);
        if ($eveAuth === null) {
            // Fails if a SSOv1 access token is still valid (up to ~20 minutes after it was created).
            // Should not happen otherwise, but could if for some reason the access token cannot be parsed.
            // Don't change the valid flag in this case.
            return self::CHECK_TOKEN_PARSE_ERROR;
        }

        // The token is valid here, a null value means no scopes
        if ($defaultEsiToken->getValidToken() === null) {
            $result = self::CHECK_TOKEN_NOK;
        } else {
            $result = self::CHECK_TOKEN_OK;
        }

        // Check owner change
        if ($eveAuth->getCharacterOwnerHash() !== '') {
            // The next check should never be true because the token is already invalid
            // after a character transfer - I hope ...
            if ($char->getCharacterOwnerHash() !== $eveAuth->getCharacterOwnerHash()) {
                $this->deleteCharacter($char, RemovedCharacter::REASON_DELETED_OWNER_CHANGED);
                $result = self::CHECK_CHAR_DELETED;
            }
        } else {
            // that's an error, CCP changed the JWT data
            $this->log->error('Unexpected JWT data, missing character owner hash.');
        }

        $this->objectManager->flush();

        return $result;
    }

    /**
     * Removes a character from it's current player account,
     * adds it to the new player and creates a RemovedCharacter record.
     *
     * Does not flush the entity manager at the end.
     */
    public function moveCharacter(Character $character, Player $newPlayer, string $reason): void
    {
        $this->createRemovedCharacter($character, $reason, $newPlayer);

        $oldPlayer = $character->getPlayer();

        $oldPlayer->removeCharacter($character);
        $character->setPlayer($newPlayer);
        $newPlayer->addCharacter($character);

        $this->assureMain($oldPlayer);
    }

    /**
     * Deletes a character and creates a RemovedCharacter record.
     *
     * Does not flush the entity manager at the end.
     *
     * @param Character $character Entity attached to the entity manager.
     */
    public function deleteCharacter(Character $character, string $reason, Player $deletedBy = null): void
    {
        $oldPlayer = $character->getPlayer();
        if ($reason === RemovedCharacter::REASON_DELETED_BY_ADMIN) {
            $this->log->info(
                'An admin (player ID: ' . ($deletedBy ? $deletedBy->getId() : 'unknown') . ') ' .
                'deleted character "' . $character->getName() . '" [' . $character->getId() . '] ' .
                'from player "' . $oldPlayer->getName() . '" [' . $oldPlayer->getId() . ']'
            );
        } else {
            $this->createRemovedCharacter($character, $reason, null, $deletedBy);
        }

        $this->objectManager->remove($character);

        $oldPlayer->removeCharacter($character);
        $this->assureMain($oldPlayer);
    }

    /**
     * Sets the oldest character (first added to Neucore) as the main if the player account doesn't have a main.
     *
     * Does not flush the entity manager.
     */
    public function assureMain(Player $player): void
    {
        $oldestCharacter = null; /* @var Character $oldestCharacter */
        $mainFound = false;
        foreach ($player->getCharacters() as $character) {
            if (
                $oldestCharacter === null ||
                $character->getCreated() === null ||
                (
                    $oldestCharacter->getCreated() !== null &&
                    $character->getCreated()->getTimestamp() < $oldestCharacter->getCreated()->getTimestamp()
                )
            ) {
                $oldestCharacter = $character;
            }
            if ($character->getMain()) {
                $mainFound = true;
                break;
            }
        }
        if (! $mainFound && $oldestCharacter !== null) {
            $oldestCharacter->setMain(true);
            $player->setName($oldestCharacter->getName());
        }
    }

    /**
     * Checks if groups are deactivated for this player.
     */
    public function groupsDeactivated(Player $player, bool $ignoreDelay = false): bool
    {
        // managed account?
        if ($player->getStatus() === Player::STATUS_MANAGED) {
            return false;
        }

        // enabled?
        $requireToken = $this->repositoryFactory->getSystemVariableRepository()->findOneBy(
            ['name' => SystemVariable::GROUPS_REQUIRE_VALID_TOKEN]
        );
        if (! $requireToken || $requireToken->getValue() === '0') {
            return false;
        }

        // get configured alliances and corporations
        $sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();
        $allianceVar = $sysVarRepo->find(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES);
        $corporationVar = $sysVarRepo->find(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS);
        if ($allianceVar === null || $corporationVar === null) {
            // Alliance and/or Corporation settings variable not found
            return false;
        }
        $alliances = array_map('intval', explode(',', $allianceVar->getValue()));
        $corporations = array_map('intval', explode(',', $corporationVar->getValue()));

        // check if player account has at least one character in one of the configured alliances or corporations
        if (! $player->hasCharacterInAllianceOrCorporation($alliances, $corporations)) {
            return false;
        }

        // get delay
        if ($ignoreDelay) {
            $hours = 0;
        } else {
            $delay = $this->repositoryFactory->getSystemVariableRepository()->findOneBy(
                ['name' => SystemVariable::ACCOUNT_DEACTIVATION_DELAY]
            );
            $hours = $delay !== null ? (int) $delay->getValue() : 0;
        }

        if ($player->hasCharacterWithInvalidTokenOlderThan($hours)) {
            return true;
        }

        return false;
    }

    /**
     * Executes the auto group assignment, syncs tracking and watchlist roles and checks required groups.
     */
    public function updateGroups(int $playerId): bool
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if ($player === null) {
            return false;
        }

        $this->autoGroupAssignment->assignDefaultGroups($player);
        $this->autoGroupAssignment->assign($player);
        $this->autoGroupAssignment->checkRequiredGroups($player);
        $this->syncManagerRole($player, Role::GROUP_MANAGER); // fix roles that were not remove due to errors
        $this->syncTrackingRole($player);
        $this->syncWatchlistRole($player);
        $this->syncWatchlistManagerRole($player);

        return $this->objectManager->flush();
    }

    /**
     * Adds or removes the "tracking" role to players based on group membership and corporation configuration.
     *
     * This function modifies one or more players and does *not* flush the object manager.
     * If the player object is provided, the corporation/group relations must be up to date in the database.
     * If the corporation object is provided, the player/group relations must be up to date in the database.
     */
    public function syncTrackingRole(Player $changedPlayer = null, Corporation $changedCorporation = null): void
    {
        // validate params
        if (($changedPlayer === null && $changedCorporation === null) ||
            ($changedPlayer !== null && $changedCorporation !== null)
        ) {
            $this->log->error('Account::syncTrackingRole(): Invalid function call.');
            return;
        }

        // collect all groups that grant the tracking role
        $groupIds = [];
        $corpIds = $this->repositoryFactory->getCorporationMemberRepository()->fetchCorporationIds();
        foreach ($this->repositoryFactory->getCorporationRepository()->findBy(['id' => $corpIds]) as $corp) {
            if ($changedCorporation && $changedCorporation->getId() === $corp->getId()) {
                // use groups from changed corporation because that may not be persisted to the database yet.
                $groupIds = array_merge($groupIds, $changedCorporation->getGroupsTrackingIds());
            } else {
                $groupIds = array_merge($groupIds, $corp->getGroupsTrackingIds());
            }
        }

        $this->syncRoleByGroupMembership(Role::TRACKING, $groupIds, $changedPlayer);
    }

    /**
     * Adds or removes the "watchlist" role from players based on group membership and watchlist configuration.
     *
     * This function modifies one or more players and does *not* flush the object manager.
     * If the player object is provided, the watchlist/group relations must be up to date in the database,
     * otherwise the watchlist/group and player/group relations must be up to date in the DB.
     */
    public function syncWatchlistRole(Player $changedPlayer = null): void
    {
        $watchlistRepository = $this->repositoryFactory->getWatchlistRepository();

        // collect all groups that grant the watchlist role
        $groupIds = [];
        foreach ($watchlistRepository->findBy([]) as $watchlist) {
            $groupIds = array_merge($groupIds, array_map(function (Group $group) {
                return $group->getId();
            }, $watchlist->getGroups()));
        }

        $this->syncRoleByGroupMembership(Role::WATCHLIST, $groupIds, $changedPlayer);
    }

    public function syncWatchlistManagerRole(Player $changedPlayer = null): void
    {
        $watchlistRepository = $this->repositoryFactory->getWatchlistRepository();

        // collect all groups that grant the watchlist-manager role
        $groupIds = [];
        foreach ($watchlistRepository->findBy([]) as $watchlist) {
            $groupIds = array_merge($groupIds, array_map(function (Group $group) {
                return $group->getId();
            }, $watchlist->getManagerGroups()));
        }

        $this->syncRoleByGroupMembership(Role::WATCHLIST_MANAGER, $groupIds, $changedPlayer);
    }

    /**
     * Adds or removes the app- or group-manager role,
     * depending on whether the player is a manager of an app or a group
     *
     * Does not flush the entity manager.
     *
     * @param Player $player Player object that is attached to the entity manager
     */
    public function syncManagerRole(Player $player, string $roleName): void
    {
        // get role
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $roleName]);
        if ($role === null) {
            $this->log->error('Account::syncGroupManagerRole(): Role not found.');
            return;
        }

        $addRole = false;
        if (
            ($roleName === Role::GROUP_MANAGER && ! empty($player->getManagerGroups())) ||
            ($roleName === Role::APP_MANAGER && ! empty($player->getManagerApps()))
        ) {
            $addRole = true;
        }

        if ($addRole && ! $player->hasRole($role->getName())) {
            $player->addRole($role);
        } elseif (! $addRole && $player->hasRole($role->getName())) {
            $player->removeRole($role);
        }
    }

    /**
     * @param int[] $groupIds Group IDs that grant the role
     */
    private function syncRoleByGroupMembership(string $roleName, array $groupIds, Player $changedPlayer = null): void
    {
        // get role
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $roleName]);
        if ($role === null) { // should not happen
            $this->log->error("Account::syncRole(): Role '$roleName' not found.");
            return;
        }

        $playerRepository = $this->repositoryFactory->getPlayerRepository();

        // get all players that need the role
        $playersAdd = [];
        if ($changedPlayer) {
            if ($changedPlayer->hasAnyGroup($groupIds)) {
                $playersAdd = [$changedPlayer];
            }
        } else {
            $playersAdd = $playerRepository->findWithGroups($groupIds);
        }

        // assign role
        foreach ($playersAdd as $playerAdd) {
            if (! $playerAdd->hasRole($role->getName())) {
                $playerAdd->addRole($role);
            }
        }

        // get all players that have the role
        $playersRemove = [];
        if ($changedPlayer) {
            if ($changedPlayer->hasRole($role->getName())) {
                $playersRemove = [$changedPlayer];
            }
        } else {
            $playersRemove = $playerRepository->findWithRole($role->getId());
        }

        // remove role if needed
        foreach ($playersRemove as $playerRemove) {
            if (! $playerRemove->hasAnyGroup($groupIds)) {
                $playerRemove->removeRole($role);
            }
        }
    }

    private function createRemovedCharacter(
        Character $character,
        string $reason,
        Player $newPlayer = null,
        Player $deletedBy = null
    ): void {
        if ($character->getId() === 0) { // should never be true, but that's not obvious here
            $this->log->error('Account::createRemovedCharacter(): Missing character ID.');
            return;
        }

        $removedCharacter = new RemovedCharacter();

        $player = $character->getPlayer();
        $removedCharacter->setPlayer($player);
        $removedCharacter->setReason($reason);
        $removedCharacter->setDeletedBy($deletedBy);
        $player->addRemovedCharacter($removedCharacter);
        $removedCharacter->setCharacterId($character->getId());
        $removedCharacter->setCharacterName($character->getName());
        try {
            $removedCharacter->setRemovedDate(new \DateTime());
        } catch (\Exception $e) {
            // ignore
        }
        if ($newPlayer) {
            $removedCharacter->setNewPlayer($newPlayer);
        }

        $this->objectManager->persist($removedCharacter);
    }
}
