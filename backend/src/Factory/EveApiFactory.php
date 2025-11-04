<?php

declare(strict_types=1);

namespace Neucore\Factory;

use Neucore\Exception\Exception;
use Neucore\Service\Config;
use GuzzleHttp\ClientInterface;
use Neucore\Service\EveMailToken;
use Tkhamez\Eve\API\Api\AllianceApi;
use Tkhamez\Eve\API\Api\CharacterApi;
use Tkhamez\Eve\API\Api\CorporationApi;
use Tkhamez\Eve\API\Api\MailApi;
use Tkhamez\Eve\API\Api\UniverseApi;
use Tkhamez\Eve\API\Configuration;

class EveApiFactory
{
    private array $instances = [];

    private ClientInterface $client;

    private string $mailToken = '';

    public function __construct(
        HttpClientFactoryInterface $httpClientFactory,
        private readonly Config $config,
        private readonly EveMailToken $eveMailToken,
    ) {
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
            if ($this->getToken($token) !== '') {
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

    private function getToken(string $token): string
    {
        if ($token !== '') {
            return $token;
        }

        if ($this->config['eve']['use_mail_token_for_unauthorised_requests'] !== '1') {
            return $token;
        }

        // Use the mail token for unauthorised requests to get better error limits.
        // See also https://developers.eveonline.com/docs/services/esi/rate-limiting/#bucket-system.

        if ($this->mailToken !== '') {
            return $this->mailToken;
        }

        try {
            $storedToken = $this->eveMailToken->getStoredToken();
        } catch (Exception) {
            return $token;
        }

        try {
            $validToken = $this->eveMailToken->getValidToken($storedToken);
        } catch (Exception) {
            return $token;
        }

        $this->mailToken = $validToken->getToken();

        return $this->mailToken;
    }
}
