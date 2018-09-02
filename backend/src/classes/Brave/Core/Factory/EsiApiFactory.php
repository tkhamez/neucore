<?php

namespace Brave\Core\Factory;

use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Configuration;

class EsiApiFactory
{
    private $instances = [];

    /**
     * @var Configuration
     */
    private $configuration;

    /**
     * Constructor to allow mock objects.
     */
    public function __construct(
        AllianceApi $allianceApi = null,
        CorporationApi $corporationApi = null,
        CharacterApi $characterApi = null
    ) {
        $this->instances[AllianceApi::class] = $allianceApi ? $allianceApi : null;
        $this->instances[CorporationApi::class] = $corporationApi ? $corporationApi : null;
        $this->instances[CharacterApi::class] = $characterApi ? $characterApi : null;
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
                    $this->instances[$class] = new AllianceApi();
                    break;
                case CorporationApi::class:
                    $this->instances[$class] = new CorporationApi();
                    break;
                case CharacterApi::class:
                    $this->instances[$class] = new CharacterApi();
                    break;
            }
        }

        return $this->instances[$class];
    }
}
