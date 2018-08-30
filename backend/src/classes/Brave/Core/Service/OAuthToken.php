<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;

/**
 * Handles OAuth tokens for ESI.
 */
class OAuthToken
{
    /**
     * @var GenericProvider
     */
    private $oauth;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var Character
     */
    private $character;

    public function __construct(GenericProvider $oauth, ObjectManager $objectManager, LoggerInterface $log)
    {
        $this->oauth = $oauth;
        $this->objectManager = $objectManager;
        $this->log = $log;
    }

    /**
     * Set the character that holds the access and refresh token.
     *
     * The entity should already be saved to the database.
     *
     * @param Character $character
     */
    public function setCharacter(Character $character)
    {
        $this->character = $character;
    }

    /**
     * Returns the access token for an EVE character.
     *
     * If the existing token has expired, a new one is fetched with the
     * refresh token and saved in the database for the character.
     *
     * @return string
     * @see OAuthToken::setCharacter()
     */
    public function getToken(): string
    {
        $existingToken = $this->createAccessTokenFromCharacter();
        if ($existingToken === null) {
            return "";
        }

        $newAccessToken = $this->refreshAccessToken($existingToken);

        if ($newAccessToken) {
            $this->character->setAccessToken($newAccessToken->getToken());
            $this->character->setExpires($newAccessToken->getExpires());
            if (! $this->objectManager->flush()) {
                return ""; // old token is invalid, new token could not be saved
            }
        }

        return $newAccessToken ? $newAccessToken->getToken() : $existingToken->getToken();
    }

    /**
     * Returns resource owner.
     *
     * Set a character first, it must contain a refresh token.
     *
     * @return null|\League\OAuth2\Client\Provider\ResourceOwnerInterface
     * @see OAuthToken::setCharacter()
     */
    public function verify()
    {
        $existingToken = $this->createAccessTokenFromCharacter();
        if ($existingToken === null) {
            return null;
        }

        $newAccessToken = $this->refreshAccessToken($existingToken);
        $token = $newAccessToken ? $newAccessToken : $existingToken;

        $owner = null;
        try {
            $owner = $this->oauth->getResourceOwner($token);
        } catch (\Exception $e) {
            // don't log "invalid_token" message as this is expected when the token is revoked
            if ($e->getMessage() !== 'invalid_token') {
                $this->log->error($e->getMessage(), ['exception' => $e]);
            }
        }

        return $owner;
    }

    /**
     * @return AccessToken|null
     */
    private function createAccessTokenFromCharacter()
    {
        if ($this->character === null) {
            $this->log->error('OAuthToken::getToken: Character not set.');
            return null;
        }

        $token = null;
        try {
            $token = new AccessToken([
                'access_token' => $this->character->getAccessToken(),
                'refresh_token' => (string) $this->character->getRefreshToken(),
                'expires' => $this->character->getExpires()
            ]);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
        }

        return $token;
    }

    /**
     * @param AccessToken $existingToken
     * @return NULL|\League\OAuth2\Client\Token\AccessToken
     */
    private function refreshAccessToken(AccessToken $existingToken)
    {
        $newAccessToken = null;
        if ($existingToken->getExpires() && $existingToken->hasExpired()) {
            try {
                $newAccessToken = $this->oauth->getAccessToken('refresh_token', [
                    'refresh_token' => (string) $existingToken->getRefreshToken()
                ]);
            } catch (\Exception $e) {
                // don't log "invalid_token" message as this is expected when the token is revoked
                if ($e->getMessage() !== 'invalid_token') {
                    $this->log->error($e->getMessage(), ['exception' => $e]);
                }
            }
        }

        return $newAccessToken;
    }
}
