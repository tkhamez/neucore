<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Player;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;

class CharacterService
{
    /**
     * Result for checkAndUpdateCharacter() if token is valid.
     */
    const CHECK_TOKEN_OK = 1;

    /**
     * Result for checkAndUpdateCharacter() if token is invalid.
     */
    const CHECK_TOKEN_NOK = 2;

    /**
     * Result for checkAndUpdateCharacter() if owner hash changed and character was deleted.
     */
    const CHECK_CHAR_DELETED = 3;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    public function __construct(
        LoggerInterface $log,
        ObjectManager $objectManager
    ) {
        $this->log = $log;
        $this->objectManager = $objectManager;
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
        $oldPlayer = $char->getPlayer();
        $oldPlayer->removeCharacter($char);

        $player = new Player();
        $player->setName($char->getName());

        $char->setMain(true);
        $char->setPlayer($player);
        $player->addCharacter($char);

        return $char;
    }

    /**
     * Update and save character and player.
     *
     * Updates character with the data provided and persists player
     * and character in the database. Both Entities can be new.
     *
     * @param Character $char Character with Player object attached.
     * @param string $characterName
     * @param string $characterOwnerHash
     * @param string $scopes
     * @param AccessToken $token A valid token
     * @return bool
     */
    public function updateAndStoreCharacterWithPlayer(
        Character $char,
        string $characterName,
        string $characterOwnerHash,
        string $scopes,
        AccessToken $token
    ): bool {
        $char->setName($characterName);
        $char->setLastLogin(new \DateTime());
        $char->setValidToken(true);

        $char->setCharacterOwnerHash($characterOwnerHash);
        $char->setScopes($scopes);

        $char->setAccessToken($token->getToken());
        $char->setExpires($token->getExpires());
        $char->setRefreshToken($token->getRefreshToken());

        $this->objectManager->persist($char->getPlayer()); // could be a new player
        $this->objectManager->persist($char); // could be a new character

        return $this->objectManager->flush();
    }

    /**
     * Checks access/refresh token and owner hash.
     *
     * The refresh token is verified by requesting a new access token
     * (If the access token is still valid the refresh token is not validated).
     *
     * If the character owner hash of the character changed the character is deleted!
     *
     * This only updates the validToken property (true/false), not the access token itself.
     *
     * All objects are saved.
     *
     * @param Character $char An instance that is attached to the Doctrine entity manager.
     * @param OAuthToken $tokenService
     * @return int self::TOKEN_NOK, self::TOKEN_OK or self::CHARACTER_DELETED
     */
    public function checkAndUpdateCharacter(Character $char, OAuthToken $tokenService): int
    {
        $resourceOwner = $tokenService->verify($char);

        if ($resourceOwner === null) {
            $char->setValidToken(false);
            $result = self::CHECK_TOKEN_NOK;
        } else {
            $char->setValidToken(true);
            $result = self::CHECK_TOKEN_OK;
            $data = $resourceOwner->toArray();
            if (isset($data['CharacterOwnerHash'])) {
                if ($char->getCharacterOwnerHash() !== $data['CharacterOwnerHash']) {
                    $this->objectManager->remove($char);
                    $result = self::CHECK_CHAR_DELETED;
                    $char = null;
                }
            } else {
                // that's an error, CCP changed resource owner data
                $this->log->error('Unexpected result from OAuth verify.', [
                    'data' => $data
                ]);
            }
        }

        $this->objectManager->flush();

        return $result;
    }
}