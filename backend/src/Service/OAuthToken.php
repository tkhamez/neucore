<?php

declare(strict_types=1);

namespace Neucore\Service;

use Eve\Sso\EveAuthentication;
use Eve\Sso\JsonWebToken;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Token\AccessToken;
use Neucore\Entity\Character;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Neucore\Entity\EsiToken;
use Neucore\Log\Context;
use Psr\Log\LoggerInterface;

/**
 * Handles OAuth tokens for ESI of a character.
 */
class OAuthToken
{
    public const OPTION_ACCESS_TOKEN = 'access_token';

    public const OPTION_REFRESH_TOKEN = 'refresh_token';

    public const OPTION_EXPIRES = 'expires';

    public const OPTION_RESOURCE_OWNER_ID = 'resource_owner_id';

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
     * @var ClientInterface
     */
    private $client;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        GenericProvider $oauth,
        ObjectManager $objectManager,
        LoggerInterface $log,
        ClientInterface $client,
        Config $config
    ) {
        $this->oauth = $oauth;
        $this->objectManager = $objectManager;
        $this->log = $log;
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Refreshes the access token if necessary and stores the new refresh token.
     */
    public function refreshEsiToken(EsiToken $esiToken): bool
    {
        $existingToken = $this->createAccessToken($esiToken);
        if ($existingToken === null) {
            return false;
        }

        try {
            $token = $this->refreshAccessToken($existingToken);
        } catch (IdentityProviderException $e) {
            // Delete invalid refresh token so that it cannot be used again.
            $esiToken->setAccessToken('');
            $esiToken->setRefreshToken('');
            $esiToken->setValidToken(false);
            $this->objectManager->flush();
            return false;
        }

        if ($token->getToken() !== $existingToken->getToken()) {
            if (!is_numeric($token->getExpires()) || !is_string($token->getRefreshToken())) {
                return false;
            }
            $esiToken->setAccessToken($token->getToken());
            $esiToken->setExpires($token->getExpires());
            $esiToken->setRefreshToken($token->getRefreshToken());
            if (!$this->objectManager->flush()) {
                return false; // old token is invalid, new token could not be saved
            }
        }

        return true;
    }

    /**
     * Refreshes the access token if necessary.
     *
     * @param AccessTokenInterface $existingToken
     * @return AccessTokenInterface A new object if the token was refreshed
     * @throws IdentityProviderException For "invalid_grant" error, other exceptions are caught.
     */
    public function refreshAccessToken(AccessTokenInterface $existingToken): AccessTokenInterface
    {
        $newToken = null;
        if ($existingToken->getExpires() && $existingToken->hasExpired()) {
            try {
                $newToken = $this->oauth->getAccessToken(self::OPTION_REFRESH_TOKEN, [
                    self::OPTION_REFRESH_TOKEN => (string) $existingToken->getRefreshToken()
                ]);
            } catch (\Exception $e) {
                if ($e instanceof IdentityProviderException && $e->getMessage() === 'invalid_grant') {
                    // invalid_grant = e. g. invalid or revoked refresh token
                    throw $e;
                } else {
                    $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
                }
            }
        }

        return $newToken ?? $existingToken;
    }

    /**
     * @param AccessTokenInterface $existingToken
     * @return bool
     * @see https://github.com/esi/esi-docs/blob/master/docs/sso/revoking_refresh_tokens.md
     */
    public function revokeRefreshToken(AccessTokenInterface $existingToken)
    {
        $conf = $this->config['eve'];
        $urls = $conf['datasource'] === 'singularity' ? $conf['oauth_urls_sisi'] : $conf['oauth_urls_tq'];

        try {
            $response = $this->client->request('POST', $urls['revoke'], [
                'auth' => [$conf['client_id'], $conf['secret_key'], 'basic'],
                'json' => [
                    'token'           => $existingToken->getRefreshToken(),
                    'token_type_hint' => self::OPTION_REFRESH_TOKEN
                ],
            ]);
        } catch (GuzzleException $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return false;
        }

        if ($response->getStatusCode() === 200) {
            return true;
        }

        $this->log->error('Error revoking token: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
        return false;
    }

    public function createAccessToken(EsiToken $esiToken): ?AccessTokenInterface
    {
        $token = null;
        try {
            $token = new AccessToken([
                'access_token' => $esiToken->getAccessToken(),
                'refresh_token' => $esiToken->getRefreshToken(),
                'expires' => (int) $esiToken->getExpires()
            ]);
        } catch (\Exception $e) {
            // characters without a default "access_token" are okay.
        }

        return $token;
    }

    public function getScopesFromToken(EsiToken $esiToken): array
    {
        $token = $this->createAccessToken($esiToken);
        if ($token === null) {
            return [];
        }
        try {
            $jwt = new JsonWebToken($token);
        } catch (\UnexpectedValueException $e) {
            return [];
        }

        return $jwt->getEveAuthentication()->getScopes();
    }

    /**
     * Returns the default access token for an EVE character.
     *
     * When the existing token has expired, a new one is fetched using the
     * refresh token and stored in the database for the character.
     *
     * @param Character $character The entity should already be saved to the database.
     * @param string $eveLoginName An EveLogin->$name
     * @return string The access token or empty string on error or if the character has no default token.
     */
    public function getToken(Character $character, string $eveLoginName): string
    {
        $esiToken = $character->getEsiToken($eveLoginName);
        if ($esiToken === null) {
            return '';
        }

        $success = $this->refreshEsiToken($esiToken);

        return $success ? $esiToken->getAccessToken() : '';
    }

    /**
     * Modifies and persists $esiToken
     *
     * @param EsiToken $esiToken An object attached to the entity manager
     */
    public function updateEsiToken(EsiToken $esiToken): ?AccessTokenInterface
    {
        $success = $this->refreshEsiToken($esiToken);
        if (!$success) {
            return null;
        }

        $token = $this->createAccessToken($esiToken);

        // The access token should be valid here, but in theory it's still possible that it cannot be parsed.
        // Check scopes (scopes should not change after login since you cannot revoke individual scopes)
        $eveAuth = $this->getEveAuth($token);
        if ($eveAuth !== null) { // null = decoding the token failed, change nothing in this case
            if (
                empty($eveAuth->getScopes()) ||
                !is_numeric($token->getExpires()) ||
                !is_string($token->getRefreshToken())
            ) {
                $esiToken->setValidToken(); // treat no scopes as if there was no token
            } else {
                $esiToken->setValidToken(true);
            }
            $this->objectManager->flush();
        }

        return $token;
    }

    public function getEveAuth(AccessTokenInterface $token): ?EveAuthentication
    {
        try {
            $jwt = new JsonWebToken($token);
        } catch (\UnexpectedValueException $e) {
            return null;
        }
        return $jwt->getEveAuthentication();
    }
}
