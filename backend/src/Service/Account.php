<?php declare(strict_types=1);

namespace Neucore\Service;

use Brave\Sso\Basics\JsonWebToken;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
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

    public function __construct(
        LoggerInterface $log,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory
    ) {
        $this->log = $log;
        $this->objectManager = $objectManager;
        $this->repositoryFactory = $repositoryFactory;
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
     * Does not persist them in the database.
     */
    public function moveCharacterToNewAccount(Character $char): Character
    {
        $newPlayer = new Player();
        $newPlayer->setName($char->getName());

        $this->removeCharacterFromPlayer($char, $newPlayer);

        $char->setMain(true);
        $char->setPlayer($newPlayer);
        $newPlayer->addCharacter($char);

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
     * @return bool
     */
    public function updateAndStoreCharacterWithPlayer(Character $char, EveAuthentication $eveAuth): bool
    {
        // update character
        $token = $eveAuth->getToken();
        $char->setName($eveAuth->getCharacterName());
        try {
            $char->setLastLogin(new \DateTime());
        } catch (\Exception $e) {
            // ignore
        }
        if (! empty($token->getRefreshToken())) {
            $char->setValidToken(true);
        } else {
            $char->setValidToken(null);
        }
        $char->setCharacterOwnerHash($eveAuth->getCharacterOwnerHash());
        $char->setScopes(implode(' ', $eveAuth->getScopes()));
        $char->setAccessToken($token->getToken());
        $char->setExpires($token->getExpires());
        $char->setRefreshToken($token->getRefreshToken());

        // update account name
        if ($char->getMain()) {
            $char->getPlayer()->setName($char->getName());
        }

        // could be a new player and/or character, so persist
        $this->objectManager->persist($char->getPlayer());
        $this->objectManager->persist($char);

        return $this->objectManager->flush();
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

        // does the char has a token?
        $existingToken = $tokenService->createAccessTokenFromCharacter($char);
        if ($existingToken === null || empty($existingToken->getRefreshToken())) {
            $char->setValidToken(null);
            $this->objectManager->flush();
            return self::CHECK_TOKEN_NA;
        }

        // validate token
        try {
            $token = $tokenService->refreshAccessToken($existingToken);
        } catch (IdentityProviderException $e) {
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
     * Removes a character from a player account and creates a RemovedCharacter record.
     *
     * **Make sure to add another player to the character!**
     *
     * Does not flush the entity manager.
     */
    public function removeCharacterFromPlayer(Character $character, Player $newPlayer): void
    {
        $this->createRemovedCharacter($character, $newPlayer);

        $character->getPlayer()->removeCharacter($character);
    }

    /**
     * Deletes a character and creates a RemovedCharacter record.
     *
     * Does not flush the entity manager.
     */
    public function deleteCharacter(Character $character, string $reason, Player $deletedBy = null): void
    {
        if ($reason === RemovedCharacter::REASON_DELETED_BY_ADMIN) {
            $this->log->info(
                'An admin (player ID: ' . ($deletedBy ? $deletedBy->getId() : 'unknown') . ') ' .
                'deleted character "' . $character->getName() . '" [' . $character->getId() . '] ' .
                'from player "' . $character->getPlayer()->getName() . '" [' . $character->getPlayer()->getId() . ']'
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
    }

    /**
     * Checks if groups are deactivated for this player.
     */
    public function groupsDeactivated(Player $player, bool $ignoreDelay = false): bool
    {
        if ($player->getStatus() === Player::STATUS_MANAGED) {
            return false;
        }

        $requireToken = $this->repositoryFactory->getSystemVariableRepository()->findOneBy(
            ['name' => SystemVariable::GROUPS_REQUIRE_VALID_TOKEN]
        );

        if ($ignoreDelay) {
            $hours = 0;
        } else {
            $delay = $this->repositoryFactory->getSystemVariableRepository()->findOneBy(
                ['name' => SystemVariable::ACCOUNT_DEACTIVATION_DELAY]
            );
            $hours = $delay !== null ? (int) $delay->getValue() : 0;
        }

        if ($requireToken && $requireToken->getValue() === '1' &&
            $player->hasCharacterWithInvalidTokenOlderThan($hours)
        ) {
            return true;
        }

        return false;
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
            $this->log->error('Account::syncTrackingRole(): Player or Role not found.');
            return;
        }

        // collect all groups that grant the tracking role
        $groupIds = [];
        foreach ($this->repositoryFactory->getCorporationRepository()->getAllWithMemberTrackingData() as $corp) {
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
        // should never be true, but that's not obvious here and setCharacterId() below needs an integer
        if ($character->getId() === null) {
            $this->log->error('Account::createRemovedCharacter(): Missing character ID.');
            return;
        }

        $removedCharacter = new RemovedCharacter();

        $player = $character->getPlayer();
        $removedCharacter->setPlayer($player);
        $removedCharacter->setDeletedBy($deletedBy);
        $player->addRemovedCharacter($removedCharacter);

        $removedCharacter->setCharacterId((int) $character->getId());
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
