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

    private ServiceRegistration $serviceRegistration;

    public function __construct(
        LoggerInterface $log,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        EsiData $esiData,
        AutoGroupAssignment $autoGroupAssignment,
        OAuthToken $tokenService,
        ServiceRegistration $serviceRegistration
    ) {
        $this->log = $log;
        $this->objectManager = $objectManager;
        $this->repositoryFactory = $repositoryFactory;
        $this->esiData = $esiData;
        $this->autoGroupAssignment = $autoGroupAssignment;
        $this->tokenService = $tokenService;
        $this->serviceRegistration = $serviceRegistration;
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
        } catch (\Exception) {
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
     * @return bool
     */
    public function updateAndStoreCharacterWithPlayer(Character $char, EveAuthentication $eveAuth): bool
    {
        $token = $eveAuth->getToken();
        // Do not update the character name here: after a character rename the name from SSO is/can be? the old name.
        // https://github.com/ccpgames/sso-issues/issues/68
        try {
            $char->setLastLogin(new \DateTime());
        } catch (\Exception) {
            // ignore
        }

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
            $esiToken->setLastChecked(new \DateTime());
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
            $this->esiData->fetchCharacterWithCorporationAndAlliance($char->getId()); // flushes
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
     * The tokens are saved if they were changed.
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
            if ($eveLogin === null || $eveLogin->getName() === EveLogin::NAME_DEFAULT) { // should not be null
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
            // after a character transfer - but apparently it can happen.
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

    public function mergeAccounts(Player $player1, Player $player2): Player
    {
        $from = $player2;
        $to = $player1;
        if ($player2->getId() < $player1->getId()) {
            $from = $player1;
            $to = $player2;
        }

        foreach ($from->getCharacters() as $characterToMove) {
            $this->moveCharacter($characterToMove, $to, RemovedCharacter::REASON_MOVED); // does not flush
            $characterToMove->setMain(false);
        }

        foreach ($from->getGroups() as $groupToMove) {
            if (!$to->hasGroup($groupToMove->getId())) {
                $to->addGroup($groupToMove);
            }
        }
        $this->updateGroups($to->getId()); // flushes entity manager
        $this->updateGroups($from->getId());

        $this->serviceRegistration->updatePlayerAccounts($to, $from);

        return $to;
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
     * Executes the auto group assignment, checks required groups for groups, tracking and watchlist roles and
     * checks required groups for roles.
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
        $this->syncManagerRole($player, Role::GROUP_MANAGER); // Only to fix roles that were not remove due to errors
        $this->syncTrackingRole($player);
        $this->syncWatchlistRole($player);
        $this->syncWatchlistManagerRole($player);

        $this->checkRoles($player);

        return $this->objectManager->flush();
    }

    /**
     * Removes roles if the player does not have a required group.
     *
     * Also removed player as a manager of groups or apps if the corresponding role was removed.
     *
     * @param Player $player Player object that is attached to the entity manager
     */
    public function checkRoles(Player $player): bool
    {
        $rolesToRemove = [];
        foreach ($player->getRoles() as $role) {
            $keepRole = empty($role->getRequiredGroups());
            foreach ($role->getRequiredGroups() as $requiredGroup) {
                if ($player->hasGroup($requiredGroup->getId())) {
                    $keepRole = true;
                }
            }
            if (!$keepRole && in_array($role->getName(), Role::ROLES_WITH_GROUP_REQUIREMENT)) {
                $rolesToRemove[] = $role;
            }
        }

        foreach ($rolesToRemove as $role) {
            if ($role->getName() === Role::GROUP_MANAGER) {
                foreach ($player->getManagerGroups() as $managerGroup) {
                    $managerGroup->removeManager($player);
                }
            } elseif ($role->getName() === Role::APP_MANAGER) {
                foreach ($player->getManagerApps() as $managerApp) {
                    $managerApp->removeManager($player);
                }
            }

            // Note: tracking, watchlist and 'watchlist-manager cannot have a group requirement,
            // so there's no conflict with that assignment which is solely based on groups.

            $player->removeRole($role);
        }

        if ($this->objectManager->flush() && !empty($rolesToRemove)) {
            $this->log->info("Removed role(s) from player {$player->getId()}.");
            return true;
        }
        return false;
    }

    /**
     * @param Player $player Player object that is attached to the entity manager
     */
    public function mayHaveRole(Player $player, string $roleName): bool
    {
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $roleName]);
        if ($role === null) {
            return false;
        }

        if (empty($role->getRequiredGroups())) {
            return true;
        }

        foreach ($role->getRequiredGroups() as $role) {
            if ($player->hasGroup($role->getId())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds or removes the "tracking" role to players based on group membership and corporation configuration.
     *
     * This function modifies one or more players and does *not* flush the object manager.
     * If the player object is provided, the corporation/group relations must be up-to-date in the database.
     * If the corporation object is provided, the player/group relations must be up-to-date in the database.
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
            $this->log->error("Account::syncGroupManagerRole(): Role '$roleName' not found.");
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
     * Removes a character from its current player account,
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
