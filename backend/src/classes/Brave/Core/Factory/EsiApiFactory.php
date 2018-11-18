<?php declare(strict_types=1);

namespace Brave\Core\Factory;

use GuzzleHttp\ClientInterface;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Api\MailApi;
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

    public function getCorporationApi(): CorporationApi
    {
        return $this->getInstance(CorporationApi::class);
    }

    public function getCharacterApi(): CharacterApi
    {
        return $this->getInstance(CharacterApi::class);
    }

    /**
     * @param string $accessToken
     * @return MailApi
     * @see OAuthToken::getToken()
     */
    public function getMailApi(string $accessToken): MailApi
    {
        return $this->getInstance(MailApi::class, $accessToken);
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
