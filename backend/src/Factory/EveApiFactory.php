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

    private ClientInterface $clientAuthorised;

    private ClientInterface $clientUnauthorised;

    public function __construct(
        HttpClientFactoryInterface $httpClientFactory,
        private readonly Config $config,
        private readonly EveMailToken $eveMailToken,
    ) {
        $headersAuthorised = [
            // This header is also set for every request, but in case this changes, add a default value.
            'X-Compatibility-Date' => $this->config['eve']['esi_compatibility_date'],
        ];
        $this->clientAuthorised = $httpClientFactory->getGuzzleClient(
            requestHeaders: $headersAuthorised,
        );

        // Use a token for unauthorised requests to get better error limits. See also
        // https://developers.eveonline.com/docs/services/esi/rate-limiting/#bucket-system.
        if (($token = $this->getToken()) !== null) {
            $headersUnauthorised = $headersAuthorised;
            $headersUnauthorised['Authorization'] = "Bearer $token";
            $this->clientUnauthorised = $httpClientFactory->getGuzzleClient(
                requestHeaders: $headersUnauthorised,
            );
        } else {
            $this->clientUnauthorised = $this->clientAuthorised;
        }
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
            $client = $this->clientUnauthorised;
            if ($token !== '') {
                $configuration->setAccessToken($token);
                $client = $this->clientAuthorised;
            }
            $configuration->setHost($this->config['eve']['esi_host']);
            // Remove the library default so that it does not use it for its requests but instead uses
            // the user agent from $this->client.
            $configuration->setUserAgent('');
            $this->instances[$key] = new $class($client, $configuration);
        }

        return $this->instances[$key];
    }

    private function getToken(): ?string
    {
        if ($this->config['eve']['use_mail_token_for_unauthorised_requests'] !== '1') {
            return null;
        }

        try {
            $storedToken = $this->eveMailToken->getStoredToken();
        } catch (Exception) {
            return null;
        }

        try {
            $validToken = $this->eveMailToken->getValidToken($storedToken);
        } catch (Exception) {
            return null;
        }

        return $validToken->getToken();
    }
}
