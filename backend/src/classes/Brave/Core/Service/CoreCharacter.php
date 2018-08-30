<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Player;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;

class CoreCharacter
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var OAuthToken
     */
    private $token;

    public function __construct(
        LoggerInterface $log,
        ObjectManager $objectManager,
        OAuthToken $token
    ) {
        $this->log = $log;
        $this->objectManager = $objectManager;
        $this->token = $token;
    }

    /**
     * Creates and stores a new Character and Player.
     *
     * This is for characters who have not signed up with EVE SSO
     * (not used at the moment).
     *
     * @param int $characterId
     * @param string $characterName
     * @return boolean
     */
    public function createCharacter(int $characterId, string $characterName)
    {
        return $this->updateAndStoreCharacterWithPlayer(
            $this->createNewPlayerWithMain($characterId, $characterName)
        );
    }

    /**
     * Creates Player and Character objects.
     *
     * Does not persist them in the database.
     *
     * @param int $characterId
     * @param string $characterName
     * @return Character
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
     * Update and save character and player.
     *
     * Updates character with the data provided and persists player
     * and character in the database. Both Entities can be new.
     *
     * @param Character $char Character with Player object attached.
     * @param string|null $characterOwnerHash Will be set to null if not provided.
     * @param AccessToken|null $token Will not be updated if not provided.
     * @param string|null $scopes Will be set to null if not provided.
     * @return bool
     */
    public function updateAndStoreCharacterWithPlayer(
        Character $char,
        string $characterOwnerHash = null,
        AccessToken $token = null,
        string $scopes = null
    ): bool {
        $char->setCharacterOwnerHash($characterOwnerHash);
        $char->setScopes($scopes);

        if ($token !== null) {
            $char->setAccessToken($token->getToken());
            $char->setExpires($token->getExpires());
            $char->setRefreshToken($token->getRefreshToken());
        }

        $this->objectManager->persist($char->getPlayer()); // could be a new player
        $this->objectManager->persist($char); // could be a new character

        return $this->objectManager->flush();
    }

    /**
     * Verifies refresh token.
     *
     * The refresh token is verified by requesting a new access token.
     * This only updates the validToken property (true/false)
     * and the character owner hash, not the access token.
     *
     * @param Character $char An instance that is attached to the Doctrine entity manager.
     * @return boolean
     */
    public function checkTokenUpdateCharacter(Character $char): bool
    {
        $this->token->setCharacter($char);
        $resourceOwner = $this->token->verify();

        if ($resourceOwner === null) {
            $char->setValidToken(false);
        } else {
            $char->setValidToken(true);
            $data = $resourceOwner->toArray();
            if (isset($data['CharacterOwnerHash'])) {
                $char->setCharacterOwnerHash($data['CharacterOwnerHash']);
            } else {
                // that's an error, OAuth changed resource owner data
                $this->log->error('Unexpected result from OAuth verify.', [
                    'data' => $data
                ]);
            }
        }

        $this->objectManager->flush();

        return $char->getValidToken();
    }
}
