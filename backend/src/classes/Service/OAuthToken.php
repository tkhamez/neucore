<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
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
     * @param AccessTokenInterface $existingToken
     * @return AccessTokenInterface A new object if the token was refreshed
     * @throws IdentityProviderException For "invalid_token" error (refresh token is expired),
     *         other exceptions are caught.
     */
    public function refreshAccessToken(AccessTokenInterface $existingToken): AccessTokenInterface
    {
        if ($existingToken->getExpires() && $existingToken->hasExpired()) {
            try {
                $newToken = $this->oauth->getAccessToken('refresh_token', [
                    'refresh_token' => (string) $existingToken->getRefreshToken()
                ]);
            } catch (\Exception $e) {
                if ($e instanceof IdentityProviderException &&
                    in_array($e->getMessage(), ['invalid_token', 'invalid_request'])
                ) {
                    // invalid_token = e. g. revoked refresh token
                    // invalid_request = e. g. no refresh token
                    throw $e;
                } else {
                    $this->log->error($e->getMessage(), ['exception' => $e]);
                }
            }
        }

        return $newToken ?? $existingToken;
    }

    /**
     * Returns the access token for an EVE character.
     *
     * When the existing token has expired, a new one is fetched using the
     * refresh token and stored in the database for the character.
     *
     * @param Character $character The entity should already be saved to the database.
     * @return string The access token or empty string on error or if the character has no token.
     */
    public function getToken(Character $character): string
    {
        $existingToken = $this->createAccessTokenFromCharacter($character);
        if ($existingToken === null) {
            return "";
        }

        try {
            $token = $this->refreshAccessToken($existingToken);
        } catch (IdentityProviderException $e) {
            return "";
        }

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
     */
    public function getResourceOwner(AccessTokenInterface $token): ?ResourceOwnerInterface
    {
        $owner = null;
        try {
            /** @noinspection PhpParamsInspection */
            // see also https://github.com/thephpleague/oauth2-client/issues/752
            $owner = $this->oauth->getResourceOwner($token);
        } catch (\Exception $e) {
            if (! $e instanceof IdentityProviderException || $e->getMessage() !== 'invalid_token') {
                // don't log "invalid_token" error as this is expected if the token was revoked
                $this->log->error($e->getMessage(), ['exception' => $e]);
            }
        }

        return $owner;
    }

    public function createAccessTokenFromCharacter(Character $character): ?AccessTokenInterface
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
