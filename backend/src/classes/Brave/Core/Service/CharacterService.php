<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Player;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;

class CharacterService
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
        $resourceOwner = $this->token->verify($char);

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
