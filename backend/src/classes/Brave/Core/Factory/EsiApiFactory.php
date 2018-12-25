<?php declare(strict_types=1);

namespace Brave\Core\Factory;

use GuzzleHttp\ClientInterface;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Api\MailApi;
use Swagger\Client\Eve\Api\UniverseApi;
use Swagger\Client\Eve\Configuration;

class EsiApiFactory
{
    private $instances = [];

    /**
     * @var ClientInterface|null
     */
    private $client = null;

    /**
     * Optionally set a client.
     */
    public function setClient(ClientInterface $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getAllianceApi(): AllianceApi
    {
        return $this->getInstance(AllianceApi::class);
    }

    /**
     * @see \League\OAuth2\Client\Token\AccessToken::getToken()
     * @see \Brave\Core\Service\OAuthToken::refreshAccessToken()
     */
    public function getCorporationApi(string $accessToken = ''): CorporationApi
    {
        return $this->getInstance(CorporationApi::class, $accessToken);
    }

    /**
     * @see \League\OAuth2\Client\Token\AccessToken::getToken()
     * @see \Brave\Core\Service\OAuthToken::refreshAccessToken()
     */
    public function getCharacterApi(string $accessToken = ''): CharacterApi
    {
        return $this->getInstance(CharacterApi::class, $accessToken);
    }

    /**
     * @see \League\OAuth2\Client\Token\AccessToken::getToken()
     * @see \Brave\Core\Service\OAuthToken::refreshAccessToken()
     */
    public function getMailApi(string $accessToken): MailApi
    {
        return $this->getInstance(MailApi::class, $accessToken);
    }

    public function getUniverseApi(): UniverseApi
    {
        return $this->getInstance(UniverseApi::class);
    }

    private function getInstance(string $class, string $token = '')
    {
        $key = $class.$token;

        if (! isset($this->instances[$key])) {
            $configuration = new Configuration();
            if ($token !== '') {
                $configuration->setAccessToken($token);
            }
            $this->instances[$key] = new $class($this->client, $configuration);
        }

        return $this->instances[$key];
    }
}
