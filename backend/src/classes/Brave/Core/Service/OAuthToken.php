<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Configuration;

/**
 * Handles OAuth tokens for ESI.
 */
class OAuthToken
{
    private $oauth;

    private $em;

    private $log;

    private $character;

    public function __construct(GenericProvider $oauth, EntityManagerInterface $em, LoggerInterface $log)
    {
        $this->oauth = $oauth;
        $this->em = $em;
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
     * Returns the configuration for the Swagger client.
     *
     * For requests that need an access token.
     *
     * @return Configuration
     */
    public function getConfiguration(): Configuration
    {
        $conf = Configuration::getDefaultConfiguration();
        $conf->setAccessToken($this->getToken());

        return $conf;
    }

    /**
     * Returns the access token for an EVE character.
     *
     * If the existing token has expired, a new one is fetched with the
     * resfresh token and saved in the database for the character.
     *
     * @return string
     * @see OAuthToken::setCharacter()
     */
    public function getToken(): string
    {
        $token = "";

        if ($this->character === null) {
            $this->log->error('OAuthToken::getToken: Character not set.');
            return $token;
        }

        try {
            $existingToken = new AccessToken([
                'access_token' => $this->character->getAccessToken(),
                'refresh_token' => $this->character->getRefreshToken(),
                'expires' => $this->character->getExpires()
            ]);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return $token;
        }

        $newAccessToken = null;
        if ($existingToken->getExpires() && $existingToken->hasExpired()) {
            try {
                $newAccessToken = $this->oauth->getAccessToken('refresh_token', [
                    'refresh_token' => $existingToken->getRefreshToken()
                ]);
            } catch (\Exception $e) {
                $this->log->error($e->getMessage(), ['exception' => $e]);
            }
        }

        if ($newAccessToken) {
            $this->character->setAccessToken($newAccessToken->getToken());
            $this->character->setExpires($newAccessToken->getExpires());
            try {
                $this->em->flush();
            } catch (\Exception $e) {
                $this->log->critical($e->getMessage(), ['exception' => $e]);
            }
        }

        return $newAccessToken ? $newAccessToken->getToken() : $existingToken->getToken();
    }
}
