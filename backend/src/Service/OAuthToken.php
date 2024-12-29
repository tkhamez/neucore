<?php

declare(strict_types=1);

namespace Neucore\Service;

use Eve\Sso\AuthenticationProvider;
use Eve\Sso\EveAuthentication;
use Eve\Sso\InvalidGrantException;
use Eve\Sso\JsonWebToken;
use League\OAuth2\Client\Token\AccessToken;
use Neucore\Entity\Character;
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

    private const TOKEN_INVALID = '__invalid__';

    private AuthenticationProvider $oauth;

    private ObjectManager $objectManager;

    private LoggerInterface $log;

    public function __construct(AuthenticationProvider $oauth, ObjectManager $objectManager, LoggerInterface $log)
    {
        $this->oauth = $oauth;
        $this->objectManager = $objectManager;
        $this->log = $log;
    }

    public function createAccessToken(EsiToken $esiToken): ?AccessTokenInterface
    {
        $token = null;
        try {
            $token = new AccessToken([
                'access_token' => $esiToken->getAccessToken(),
                'refresh_token' => $esiToken->getRefreshToken(),
                'expires' => (int) $esiToken->getExpires(),
            ]);
        } catch (\Exception) {
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
        } catch (\UnexpectedValueException) {
            return [];
        }

        return $jwt->getEveAuthentication()->getScopes();
    }

    /**
     * Returns an access token for an EVE character.
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

        $token = $this->updateEsiToken($esiToken);

        return $token ? $token->getToken() : '';
    }

    /**
     * Modifies and persists $esiToken
     *
     * @param EsiToken $esiToken An object attached to the entity manager
     */
    public function updateEsiToken(EsiToken $esiToken): ?AccessTokenInterface
    {
        if (!$esiToken->getValidToken() || empty($esiToken->getRefreshToken())) {
            // Do not try to refresh an already invalid token.
            return null;
        }

        $token = $this->refreshEsiToken($esiToken);
        if (!$token) {
            return null;
        }

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
        } catch (\UnexpectedValueException) {
            return null;
        }
        return $jwt->getEveAuthentication();
    }

    /**
     * Refreshes the access token if necessary and stores the new access and refresh token.
     */
    private function refreshEsiToken(EsiToken $esiToken): ?AccessTokenInterface
    {
        $existingToken = $this->createAccessToken($esiToken);
        if ($existingToken === null) {
            if (empty($esiToken->getRefreshToken())) {
                return null;
            }
            // Refresh token may still be valid even if it failed before. For example, when EVE app client
            // configuration was temporarily wrong. In such a case the "valid_token" flag can manually be set to 1
            // in the database to try again.
            $existingToken = new AccessToken([
                'access_token' => self::TOKEN_INVALID,
                'refresh_token' => $esiToken->getRefreshToken(),
                'expires' => time() - 1000,
            ]);
        }

        try {
            $token = $this->oauth->refreshAccessToken($existingToken);
        } catch (InvalidGrantException) {
            // Delete access token and set "valid" flag, but do not delete the refresh token (just in case
            // there was some kind of error, and it is in fact still valid).
            $esiToken->setAccessToken('');
            $esiToken->setLastChecked(new \DateTime());
            $esiToken->setValidToken(false);
            $this->objectManager->flush();
            return null;
        } catch (\RuntimeException $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            $token = $existingToken;
        }

        if ($token->getToken() === self::TOKEN_INVALID) {
            return null;
        }

        if ($token->getToken() !== $existingToken->getToken()) {
            if (!is_numeric($token->getExpires()) || !is_string($token->getRefreshToken())) {
                return null;
            }
            $esiToken->setAccessToken($token->getToken());
            $esiToken->setRefreshToken($token->getRefreshToken());
            $esiToken->setLastChecked(new \DateTime());
            $esiToken->setExpires($token->getExpires());
            if (!$this->objectManager->flush()) {
                return null; // old token is invalid, new token could not be saved
            }
        }

        return $token;
    }
}
