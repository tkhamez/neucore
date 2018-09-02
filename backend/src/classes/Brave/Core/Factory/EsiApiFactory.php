<?php declare(strict_types=1);

namespace Brave\Core\Factory;

use GuzzleHttp\ClientInterface;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Configuration;

class EsiApiFactory
{
    private $instances = [];

    /**
     * @var ClientInterface|null
     */
    private $client = null;

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * Optionally set a client.
     */
    public function setClient(ClientInterface $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Set the access token for requests that need it.
     *
     * @param string $token Access token from OAuthToken->getToken()
     * @return EsiApiFactory
     * @see OAuthToken::getToken()
     */
    public function setToken(string $token): self
    {
        $this->configuration = Configuration::getDefaultConfiguration();
        $this->configuration->setAccessToken($token);

        return $this;
    }

    public function getAllianceApi(): AllianceApi
    {
        return $this->getInstance(AllianceApi::class);
    }

    public function getCorporationApi(): CorporationApi
    {
        return $this->getInstance(CorporationApi::class);
    }

    public function getCharacterApi(): CharacterApi
    {
        return $this->getInstance(CharacterApi::class);
    }

    private function getInstance(string $class)
    {
        if (! isset($this->instances[$class])) {
            switch ($class) {
                case AllianceApi::class:
                    $this->instances[$class] = new AllianceApi($this->client);
                    break;
                case CorporationApi::class:
                    $this->instances[$class] = new CorporationApi($this->client);
                    break;
                case CharacterApi::class:
                    $this->instances[$class] = new CharacterApi($this->client);
                    break;
            }
        }

        return $this->instances[$class];
    }
}
