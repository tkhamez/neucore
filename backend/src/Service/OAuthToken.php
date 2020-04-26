<?php

declare(strict_types=1);

namespace Neucore\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Neucore\Entity\Character;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Neucore\Log\Context;
use Psr\Log\LoggerInterface;

/**
 * Handles OAuth tokens for ESI of a character.
 */
class OAuthToken
{
    const OPTION_ACCESS_TOKEN = 'access_token';

    const OPTION_REFRESH_TOKEN = 'refresh_token';

    const OPTION_EXPIRES = 'expires';

    const OPTION_RESOURCE_OWNER_ID = 'resource_owner_id';

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
        $existingToken = $character->createAccessToken();
        if ($existingToken === null) {
            return '';
        }

        try {
            $token = $this->refreshAccessToken($existingToken);
        } catch (IdentityProviderException $e) {
            return '';
        }

        if ($token->getToken() !== $existingToken->getToken()) {
            $character->setAccessToken($token->getToken());
            $character->setExpires($token->getExpires());
            $character->setRefreshToken($token->getRefreshToken());
            if (! $this->objectManager->flush()) {
                return ''; // old token is invalid, new token could not be saved
            }
        }

        return $token->getToken();
    }
}
