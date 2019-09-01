<?php declare(strict_types=1);

namespace Neucore\Factory;

use Neucore\Service\Config;
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
     * @var ClientInterface
     */
    private $client;

    private $config;

    public function __construct(ClientInterface $client, Config $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    public function getAllianceApi(): AllianceApi
    {
        return $this->getInstance(AllianceApi::class);
    }

    /**
     * @see \League\OAuth2\Client\Token\AccessToken::getToken()
     * @see \Neucore\Service\OAuthToken::refreshAccessToken()
     */
    public function getCorporationApi(string $accessToken = ''): CorporationApi
    {
        return $this->getInstance(CorporationApi::class, $accessToken);
    }

    /**
     * @see \League\OAuth2\Client\Token\AccessToken::getToken()
     * @see \Neucore\Service\OAuthToken::refreshAccessToken()
     */
    public function getCharacterApi(string $accessToken = ''): CharacterApi
    {
        return $this->getInstance(CharacterApi::class, $accessToken);
    }

    /**
     * @see \League\OAuth2\Client\Token\AccessToken::getToken()
     * @see \Neucore\Service\OAuthToken::refreshAccessToken()
     */
    public function getMailApi(string $accessToken): MailApi
    {
        return $this->getInstance(MailApi::class, $accessToken);
    }

    public function getUniverseApi(string $accessToken = ''): UniverseApi
    {
        return $this->getInstance(UniverseApi::class, $accessToken);
    }

    private function getInstance(string $class, string $token = '')
    {
        $key = $class.$token;

        if (! isset($this->instances[$key])) {
            $configuration = new Configuration();
            if ($token !== '') {
                $configuration->setAccessToken($token);
            }
            $configuration->setHost($this->config['eve']['esi_host']);
            $this->instances[$key] = new $class($this->client, $configuration);
        }

        return $this->instances[$key];
    }
}
