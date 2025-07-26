<?php

declare(strict_types=1);

namespace Neucore\Factory;

use Neucore\Service\Config;
use GuzzleHttp\ClientInterface;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\MailApi;
use Swagger\Client\Eve\Api\UniverseApi;
use Tkhamez\Eve\API\Api\CorporationApi;
use Tkhamez\Eve\API\Configuration;

class EsiApiFactory
{
    private array $instances = [];

    private ClientInterface $client;

    private Config $config;

    public function __construct(HttpClientFactoryInterface $httpClientFactory, Config $config)
    {
        $this->config = $config;

        // Note: This is only necessary for tkhamez/swagger-eve-php because tkhamez/eve-api-php
        // sets it for every request.
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
            if (str_starts_with($class, 'Tkhamez\Eve\API')) {
                $configuration = new Configuration();
            } else {
                $configuration = new \Swagger\Client\Eve\Configuration();
            }
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
