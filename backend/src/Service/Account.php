<?php

declare(strict_types=1);

namespace Neucore\Service;

use Brave\Sso\Basics\JsonWebToken;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Brave\Sso\Basics\EveAuthentication;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;

class Account
{
    /**
     * Result for checkCharacter() if token is valid.
     */
    const CHECK_TOKEN_OK = 1;

    /**
     * Result for checkCharacter() if token is invalid.
     */
    const CHECK_TOKEN_NOK = 2;

    /**
     * Result for checkCharacter() if the character was deleted.
     */
    const CHECK_CHAR_DELETED = 3;

    /**
     * Result for checkCharacter() if token could not be parsed.
     */
    const CHECK_TOKEN_PARSE_ERROR = 4;

    /**
     * Result for checkCharacter() if there is no refresh token.
     */
    const CHECK_TOKEN_NA = 5;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var EsiData
     */
    private $esiData;

    /**
     * @var AutoGroupAssignment
     */
    private $autoGroupAssignment;

    public function __construct(
        LoggerInterface $log,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        EsiData $esiData,
        AutoGroupAssignment $autoGroupAssignment
    ) {
        $this->log = $log;
        $this->objectManager = $objectManager;
        $this->repositoryFactory = $repositoryFactory;
        $this->esiData = $esiData;
        $this->autoGroupAssignment = $autoGroupAssignment;
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
     * Moves character to a new player.
     *
     * Does not flush the entity manager.
     */
    public function moveCharacterToNewAccount(Character $char): Character
    {
        $newPlayer = new Player();
        $newPlayer->setName($char->getName());
        $this->objectManager->persist($newPlayer);

        $this->moveCharacter($char, $newPlayer);

        $char->setMain(true);

        return $char;
    }

    /**
     * Update and save character and player after a successful login.
     *
     * Updates character with the data provided and persists player
     * and character in the database. Both Entities can be new.
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
        $char->setName($eveAuth->getCharacterName());
        try {
            $char->setLastLogin(new \DateTime());
        } catch (\Exception $e) {
            // ignore
        }
        $char->setCharacterOwnerHash($eveAuth->getCharacterOwnerHash());
        $char->setAccessToken($token->getToken());
        $char->setExpires($token->getExpires());
        $char->setRefreshToken($token->getRefreshToken());
        if (count($char->getScopesFromToken()) > 0) {
            $char->setValidToken(true);
        } else {
            $char->setValidToken(null);
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

    /**
     * Checks if char was biomassed, the access/refresh token and the owner hash.
     *
     * The refresh token is verified by requesting a new access token
     * (If the access token is still valid the refresh token is not validated).
     *
     * The character is deleted if:
     * - the character owner hash changed
     * - the character was biomassed
     *
     * This only updates the validToken property (true/false), not the access token itself.
     *
     * The character is saved if it was changed.
     *
     * @param Character $char An instance that is attached to the Doctrine entity manager.
     * @param OAuthToken $tokenService
     * @return int self::TOKEN_NOK, self::TOKEN_OK or self::CHARACTER_DELETED
     */
    public function checkCharacter(Character $char, OAuthToken $tokenService): int
    {
        // check if character is in Doomheim (biomassed)
        if ($char->getCorporation() !== null && $char->getCorporation()->getId() === 1000001) {
            $this->deleteCharacter($char, RemovedCharacter::REASON_DELETED_BIOMASSED);
            $this->objectManager->flush();
            return self::CHECK_CHAR_DELETED;
        }

        // does the char have a token?
        if ($char->getRefreshToken() === null) {
            // Only true for SSOv1 without scopes or if the character was added by an admin.
            $char->setValidToken(null);
            $this->objectManager->flush();
            return self::CHECK_TOKEN_NA;
        }

        // validate token
        $token = null;
        if (($existingToken = $char->createAccessToken()) !== null) {
            try {
                $token = $tokenService->refreshAccessToken($existingToken);
            } catch (IdentityProviderException $e) {
                // do nothing
            }
        }
        if ($token === null) {
            $char->setAccessToken('');
            $char->setRefreshToken('');
            $char->setValidToken(false);
            $this->objectManager->flush();
            return self::CHECK_TOKEN_NOK;
        }

        // get token data
        try {
            $jwt = new JsonWebToken($token);
        } catch (\UnexpectedValueException $e) {
            // Fails if a SSOv1 access token is still valid (up to ~20 minutes after it was created).
            // Should not happen otherwise. Don't change the valid flag in this case.
            return self::CHECK_TOKEN_PARSE_ERROR;
        }
        $eveAuth = $jwt->getEveAuthentication();
        
        // token is valid here, check scopes
        // (scopes should not change after login since you cannot revoke individual scopes)
        if (count($eveAuth->getScopes()) === 0) {
            $char->setValidToken(null);
            $result = self::CHECK_TOKEN_NOK;
        } else {
            $char->setValidToken(true);
            $char->setAccessToken($token->getToken());
            $char->setExpires($token->getExpires());
            $char->setRefreshToken($token->getRefreshToken());
            $result = self::CHECK_TOKEN_OK;
        }

        // Check owner change
        if ($eveAuth->getCharacterOwnerHash() !== '') {
            // This check should never be true because the token is already invalid
            // after a character transfer - I hope ...
            if ($char->getCharacterOwnerHash() !== $eveAuth->getCharacterOwnerHash()) {
                $this->deleteCharacter($char, RemovedCharacter::REASON_DELETED_OWNER_CHANGED);
                $result = self::CHECK_CHAR_DELETED;
                $char = null;
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
    public function moveCharacter(Character $character, Player $newPlayer): void
    {
        $this->createRemovedCharacter($character, $newPlayer);

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
            $this->createRemovedCharacter($character, null, $reason, $deletedBy);
        }

        // remove corporation member reference
        $corporationMember = $this->repositoryFactory->getCorporationMemberRepository()->find($character->getId());
        if ($corporationMember !== null) {
            $corporationMember->setCharacter(null);
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
            if ($oldestCharacter === null) {
                $oldestCharacter = $character;
            } elseif (
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

        $this->autoGroupAssignment->assign($player);
        $this->syncTrackingRole($player);
        $this->syncWatchlistRole($player);
        $this->autoGroupAssignment->checkRequiredGroups($player);

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

        // get role
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => Role::TRACKING]);
        if ($role === null) { // should not happen
            $this->log->error('Account::syncTrackingRole(): Role not found.');
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
        
        // get all players that need the role
        $playersAdd = [];
        if ($changedPlayer) {
            if ($changedPlayer->hasAnyGroup($groupIds)) {
                $playersAdd = [$changedPlayer];
            }
        } else {
            $playersAdd = $this->repositoryFactory->getPlayerRepository()->findWithGroups($groupIds);
        }
        
        // assign role
        foreach ($playersAdd as $playerAdd) {
            if (! $playerAdd->hasRole(Role::TRACKING)) {
                $playerAdd->addRole($role);
            }
        }

        // get all players that have the role
        if ($changedPlayer) {
            $playersRemove = [$changedPlayer];
        } else {
            $playersRemove = $this->repositoryFactory->getPlayerRepository()->findWithRole($role->getId());
        }

        // remove role
        foreach ($playersRemove as $playerRemove) {
            if (! $playerRemove->hasAnyGroup($groupIds)) {
                $playerRemove->removeRole($role);
            }
        }
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
        // get role
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => Role::WATCHLIST]);
        if ($role === null) { // should not happen
            $this->log->error('Account::syncWatchlistRole(): Role not found.');
            return;
        }

        $watchlistRepository = $this->repositoryFactory->getWatchlistRepository();
        $playerRepository = $this->repositoryFactory->getPlayerRepository();

        // collect all groups that grant the watchlist role
        $groupIds = [];
        foreach ($watchlistRepository->findBy([]) as $watchlist) {
            $groupIds = array_merge($groupIds, array_map(function (Group $group) {
                return $group->getId();
            }, $watchlist->getGroups()));
        }

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
            if (! $playerAdd->hasRole(Role::WATCHLIST)) {
                $playerAdd->addRole($role);
            }
        }

        // get all players that have the role
        if ($changedPlayer) {
            $playersRemove = [$changedPlayer];
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

    /**
     * @param Character $character
     * @param Player|null $newPlayer
     * @param string|null $reason should be string if $newPlayer is null otherwise null
     * @return void
     */
    private function createRemovedCharacter(
        Character $character,
        Player $newPlayer = null,
        string $reason = null,
        Player $deletedBy = null
    ): void {
        if ($character->getId() === 0) { // should never be true, but that's not obvious here
            $this->log->error('Account::createRemovedCharacter(): Missing character ID.');
            return;
        }

        $removedCharacter = new RemovedCharacter();

        $player = $character->getPlayer();
        $removedCharacter->setPlayer($player);
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
            $removedCharacter->setReason(RemovedCharacter::REASON_MOVED);
        } elseif ($reason !== null) { // should never be null
            $removedCharacter->setReason($reason);
        }

        $this->objectManager->persist($removedCharacter);
    }
}
