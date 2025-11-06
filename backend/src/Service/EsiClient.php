<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Data\EsiErrorLimit;
use Neucore\Data\EsiRateLimit;
use Neucore\Entity\EveLogin;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Factory\RepositoryFactory;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class EsiClient
{
    public function __construct(
        private readonly RepositoryFactory          $repositoryFactory,
        private readonly Config                     $config,
        private readonly OAuthToken                 $tokenService,
        private readonly HttpClientFactoryInterface $httpClientFactory,
    ) {}

    /**
     * Returns the time (Unix timestamp) to wait until when the error limit was reached.
     *
     * Returns 0 if the error limit is not yet reached.
     *
     * @param int $limitRemain Number of errors that should remain.
     * @return int Unix timestamp up to which must be waited.
     * @see https://developers.eveonline.com/docs/services/esi/best-practices/#error-limit
     */
    public static function getErrorLimitWaitTime(StorageInterface $storage, int $limitRemain): int
    {
        $data = EsiErrorLimit::fromJson((string) $storage->get(Variables::ESI_ERROR_LIMIT));

        if (!$data->updated || !$data->remain || !$data->reset) {
            return 0;
        }

        $resetTime = $data->updated + $data->reset;

        if ($resetTime < time()) {
            return 0;
        }

        if ($data->remain <= $limitRemain) {
            return $resetTime;
        }

        return 0;
    }

    /**
     * Returns the time (Unix timestamp) to wait until when the ESI rate limit was reached.
     */
    public static function getRateLimitWaitTime(StorageInterface $storage): int
    {
        return (int) $storage->get(Variables::ESI_RATE_LIMITED);
    }

    /**
     * Returns the time (Unix timestamp) to wait until when temporarily throttled.
     */
    public static function getThrottledWaitTime(StorageInterface $storage): int
    {
        return (int) $storage->get(Variables::ESI_THROTTLED);
    }

    /**
     * @throws RuntimeException If character or a valid token could not be found.
     * @throws ClientExceptionInterface On request error.
     */
    public function request(
        string $esiPath,
        string $method,
        ?string $body,
        ?int $characterId,
        string $eveLoginName = EveLogin::NAME_DEFAULT,
        bool $debug = false,
        ?string $compatibilityDate = null,
        ?string $acceptLanguage = null,
    ): ResponseInterface {
        $url = $this->config['eve']['esi_host'] . $esiPath;

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

        $requestHeaders = [
            'X-Compatibility-Date' => $compatibilityDate ?: $this->config['eve']['esi_compatibility_date'],
            'Accept-Language' => $acceptLanguage ?: 'en',
        ];
        if ($debug) {
            $httpClient = $this->httpClientFactory->get(null, $requestHeaders);
        } else {
            $httpClient = $this->httpClientFactory->get(
                $characterId ? "$eveLoginName.$characterId" : $eveLoginName,
                $requestHeaders,
            );
        }

        return $httpClient->sendRequest($request);
    }
}
