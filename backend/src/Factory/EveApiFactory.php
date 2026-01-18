<?php

declare(strict_types=1);

namespace Neucore\Factory;

use Neucore\Service\Config;
use Neucore\Service\EveMailToken;
use Tkhamez\Eve\API\Api\AllianceApi;
use Tkhamez\Eve\API\Api\CharacterApi;
use Tkhamez\Eve\API\Api\CorporationApi;
use Tkhamez\Eve\API\Api\MailApi;
use Tkhamez\Eve\API\Api\UniverseApi;
use Tkhamez\Eve\API\Configuration;

class EveApiFactory
{
    public function __construct(
        private readonly HttpClientFactoryInterface $httpClientFactory,
        private readonly Config $config,
        private readonly EveMailToken $eveMailToken,
    ) {}

    public function getAllianceApi(): AllianceApi
    {
        return $this->getInstance(AllianceApi::class);
    }

    /**
     * Due to the way rate limit buckets work, you must create separate instances for
     * authenticated and unauthenticated requests.
     * For authenticated requests, specify both $accessToken and $characterId.
     */
    public function getCorporationApi(?string $accessToken = null, ?int $characterId = null): CorporationApi
    {
        return $this->getInstance(CorporationApi::class, $accessToken, $characterId);
    }

    /**
     * Due to the way rate limit buckets work, you must create separate instances for
     * authenticated and unauthenticated requests.
     * For authenticated requests, specify both $accessToken and $characterId.
     */
    public function getCharacterApi(?string $accessToken = null, ?int $characterId = null): CharacterApi
    {
        return $this->getInstance(CharacterApi::class, $accessToken, $characterId);
    }

    public function getMailApi(string $accessToken, int $characterId): MailApi
    {
        return $this->getInstance(MailApi::class, $accessToken, $characterId);
    }

    /**
     * Due to the way rate limit buckets work, you must create separate instances for
     * authenticated and unauthenticated requests.
     * For authenticated requests, specify both $accessToken and $characterId.
     */
    public function getUniverseApi(?string $accessToken = null, ?int $characterId = null): UniverseApi
    {
        return $this->getInstance(UniverseApi::class, $accessToken, $characterId);
    }

    private function getInstance(string $class, ?string $token = null, ?int $characterId = null): mixed
    {
        $configuration = new Configuration();
        $configuration->setHost($this->config['eve']['esi_host']);
        // Remove the library default so that it does not use it for its requests but instead uses
        // the user agent from $this->client.
        $configuration->setUserAgent('');

        $authenticated = false;
        if ($token !== null && $characterId !== null) {
            $authenticated = true;
            $configuration->setAccessToken($token);
        }

        $headers = [
            // This header is also set for every request, but in case this changes, add a default value.
            'X-Compatibility-Date' => $this->config['eve']['esi_compatibility_date'],
        ];
        if (!$authenticated && ($tokenUnauthenticated = $this->getToken()) !== null) {
            $headers['Authorization'] = "Bearer $tokenUnauthenticated";
        }

        $client = $this->httpClientFactory->getGuzzleClient(
            $characterId ? "eve.api.$characterId" : "eve.api",
            $headers,
            $authenticated ? $characterId : null,
        );

        return new $class($client, $configuration);
    }

    private function getToken(): ?string
    {
        if ($this->config['eve']['use_mail_token_for_unauthenticated_requests'] !== '1') {
            return null;
        }

        return $this->eveMailToken->getAccessToken();
    }
}
