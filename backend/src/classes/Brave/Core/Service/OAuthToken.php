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

    public function __construct(GenericProvider $oauth, ObjectManager $objectManager, LoggerInterface $log)
    {
        $this->oauth = $oauth;
        $this->objectManager = $objectManager;
        $this->log = $log;
    }

    /**
     * Returns the access token for an EVE character.
     *
     * If the existing token has expired, a new one is fetched with the
     * refresh token and saved in the database for the character.
     *
     * @param Character $character The entity should already be saved to the database.
     * @return string
     */
    public function getToken(Character $character): string
    {
        $existingToken = $this->createAccessTokenFromCharacter($character);
        if ($existingToken === null) {
            return "";
        }

        $newAccessToken = $this->refreshAccessToken($existingToken);

        if ($newAccessToken) {
            $character->setAccessToken($newAccessToken->getToken());
            $character->setExpires($newAccessToken->getExpires());
            if (! $this->objectManager->flush()) {
                return ""; // old token is invalid, new token could not be saved
            }
        }

        return $newAccessToken ? $newAccessToken->getToken() : $existingToken->getToken();
    }

    /**
     * Returns resource owner.
     *
     * @param Character $character It must contain a refresh token
     * @return null|\League\OAuth2\Client\Provider\ResourceOwnerInterface
     */
    public function verify(Character $character)
    {
        $existingToken = $this->createAccessTokenFromCharacter($character);
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
    private function createAccessTokenFromCharacter(Character $character)
    {
        $token = null;
        try {
            $token = new AccessToken([
                'access_token' => $character->getAccessToken(),
                'refresh_token' => (string) $character->getRefreshToken(),
                'expires' => $character->getExpires()
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
