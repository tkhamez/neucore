<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;

/**
 * Handles OAuth tokens for ESI of a character.
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
     * Refreshes the access token if necessary.
     *
     * @param AccessToken $existingToken
     * @return \League\OAuth2\Client\Token\AccessToken
     */
    public function refreshAccessToken(AccessToken $existingToken): ?AccessToken
    {
        $token = $existingToken;
        if ($existingToken->getExpires() && $existingToken->hasExpired()) {
            try {
                $token = $this->oauth->getAccessToken('refresh_token', [
                    'refresh_token' => (string) $existingToken->getRefreshToken()
                ]);
            } catch (\Exception $e) {
                // don't log an "invalid_token" message, as this is expected if the token was revoked
                if ($e->getMessage() !== 'invalid_token') {
                    $this->log->error($e->getMessage(), ['exception' => $e]);
                }
            }
        }

        return $token;
    }

    /**
     * Returns the access token for an EVE character.
     *
     * When the existing token has expired, a new one is fetched using the
     * refresh token and stored in the database for the character.
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

        $token = $this->refreshAccessToken($existingToken);

        if ($token->getToken() !== $existingToken->getToken()) {
            $character->setAccessToken($token->getToken());
            $character->setExpires($token->getExpires());
            if (! $this->objectManager->flush()) {
                return ""; // old token is invalid, new token could not be saved
            }
        }

        return $token->getToken();
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

        $token = $this->refreshAccessToken($existingToken);

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
}
