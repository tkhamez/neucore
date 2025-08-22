<?php

declare(strict_types=1);

namespace Neucore\Factory;

use Neucore\Service\Config;
use GuzzleHttp\ClientInterface;
use Tkhamez\Eve\API\Api\AllianceApi;
use Tkhamez\Eve\API\Api\CharacterApi;
use Tkhamez\Eve\API\Api\CorporationApi;
use Tkhamez\Eve\API\Api\MailApi;
use Tkhamez\Eve\API\Api\UniverseApi;
use Tkhamez\Eve\API\Configuration;

class EsiApiFactory
{
    private array $instances = [];

    private ClientInterface $client;

    private Config $config;

    public function __construct(HttpClientFactoryInterface $httpClientFactory, Config $config)
    {
        $this->config = $config;

        // This header is also set for every request, but in case this changes, add a default value.
        $this->client = $httpClientFactory->getGuzzleClient(requestHeaders: [
            'X-Compatibility-Date' => $this->config['eve']['esi_compatibility_date'],
        ]);
    }

    public function getAllianceApi(): AllianceApi
    {
        return $this->getInstance(AllianceApi::class);
    }

    public function getCorporationApi(string $accessToken = ''): CorporationApi
    {
        return $this->getInstance(CorporationApi::class, $accessToken);
    }

    public function getCharacterApi(string $accessToken = ''): CharacterApi
    {
        return $this->getInstance(CharacterApi::class, $accessToken);
    }

    public function getMailApi(string $accessToken): MailApi
    {
        return $this->getInstance(MailApi::class, $accessToken);
    }

    public function getUniverseApi(string $accessToken = ''): UniverseApi
    {
        return $this->getInstance(UniverseApi::class, $accessToken);
    }

    private function getInstance(string $class, string $token = ''): mixed
    {
        $key = $class . hash('sha256', $token);

        if (!isset($this->instances[$key])) {
            $configuration = new Configuration();
            if ($token !== '') {
                $configuration->setAccessToken($token);
            }
            $configuration->setHost($this->config['eve']['esi_host']);
            // Remove the library default so that it does not use it for its requests but instead uses
            // the user agent from $this->client.
            $configuration->setUserAgent('');
            $this->instances[$key] = new $class($this->client, $configuration);
        }

        return $this->instances[$key];
    }
}
