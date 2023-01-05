<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\EveLogin;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Factory\RepositoryFactory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class EsiClient
{
    private RepositoryFactory $repositoryFactory;

    private Config $config;

    private OAuthToken $tokenService;

    private HttpClientFactoryInterface $httpClientFactory;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        Config $config,
        OAuthToken $tokenService,
        HttpClientFactoryInterface $httpClientFactory,
    ) {
        $this->repositoryFactory = $repositoryFactory;
        $this->httpClientFactory = $httpClientFactory;
        $this->config = $config;
        $this->tokenService = $tokenService;
    }

    /**
     * @throws RuntimeException If character or a valid token could not be found.
     * @throws ClientExceptionInterface On request error.
     * @phan-suppress PhanTypeInvalidThrowsIsInterface
     */
    public function request(
        string $esiPath,
        string $method,
        ?string $body,
        ?int $characterId,
        string $eveLoginName = EveLogin::NAME_DEFAULT,
        bool $debug = false,
    ): ResponseInterface {
        $url = $this->config['eve']['esi_host'] . $esiPath.
            (strpos($esiPath, '?') ? '&' : '?') .
            'datasource=' . $this->config['eve']['datasource'];

        $header = [];
        if ($characterId) {
            $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
            if ($character === null) {
                throw new RuntimeException('Character not found.', 568420);
            }

            $token = $this->tokenService->getToken($character, $eveLoginName);
            if ($token === '') {
                throw new RuntimeException('Character has no valid token.', 568421);
            }
            $header = ['Authorization' => 'Bearer ' . $token];
        }

        $request = $this->httpClientFactory->createRequest($method, $url, $header, $body);

        if ($debug) {
            $httpClient = $this->httpClientFactory->get(null);
        } else {
            $httpClient = $this->httpClientFactory->get($eveLoginName);
        }

        return $httpClient->sendRequest($request);
    }
}
