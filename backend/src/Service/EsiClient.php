<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Application;
use Neucore\Data\EsiErrorLimit;
use Neucore\Data\EsiRateLimit;
use Neucore\Entity\EveLogin;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Factory\RepositoryFactory;
use Neucore\Storage\StorageDatabaseInterface;
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
        private readonly EveMailToken               $eveMailToken,
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
    public static function getErrorLimitWaitTime(StorageDatabaseInterface $storage, int $limitRemain): int
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
    public static function getRateLimitedWaitTime(StorageDatabaseInterface $storage): int
    {
        return (int) $storage->get(Variables::ESI_RATE_LIMITED);
    }

    /**
     * Returns the time (Unix timestamp) to wait until when temporarily throttled.
     */
    public static function getThrottledWaitTime(StorageDatabaseInterface $storage): int
    {
        return (int) $storage->get(Variables::ESI_THROTTLED);
    }

    /**
     * @return array<string, EsiRateLimit>
     */
    public static function getRateLimits(StorageDatabaseInterface $storage): array
    {
        return EsiRateLimit::fromJson((string) $storage->get(Variables::ESI_RATE_LIMIT));
    }

    public static function isPublicPath(string $esiPath): bool
    {
        $path = $esiPath;
        if (
            str_starts_with($esiPath, '/latest/') ||
            preg_match("@^/v([0-9])+/@", $esiPath) === 1
        ) {
            // Strip the version from the old paths.
            $path = substr($esiPath, (int) strpos($esiPath, '/', 1));
        }

        $publicPaths = Application::loadFile('esi-paths-public.php');

        foreach ($publicPaths as $pattern) {
            if (preg_match("@^$pattern(/)?$@", $path) === 1) {
                return true;
            }
        }

        return false;
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
        $isPublicPath = self::isPublicPath($esiPath);

        $token = null;
        if ($characterId) {
            $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
            if ($character === null) {
                throw new RuntimeException('Character not found.', 568420);
            }

            $token = $this->tokenService->getToken($character, $eveLoginName);
            if ($token === '') {
                throw new RuntimeException('Character has no valid token.', 568421);
            }
        } elseif ($isPublicPath) {
            // This is currently only relevant for requests from plugins.
            if ($this->config['eve']['use_mail_token_for_unauthenticated_requests'] === '1') {
                $token = $this->eveMailToken->getAccessToken();
            }
        }

        $header = [];
        if ($token !== null) {
            $header = ['Authorization' => 'Bearer ' . $token];
        }

        $url = $this->config['eve']['esi_host'] . $esiPath;
        $request = $this->httpClientFactory->createRequest($method, $url, $header, $body);

        $requestHeaders = [
            'X-Compatibility-Date' => $compatibilityDate ?: $this->config['eve']['esi_compatibility_date'],
            'Accept-Language' => $acceptLanguage ?: 'en',
        ];
        if ($debug) {
            $httpClient = $this->httpClientFactory->get(null, $requestHeaders);
        } else {
            $charId = $isPublicPath ? null : $characterId;
            $httpClient = $this->httpClientFactory->get(
                $charId ? "$eveLoginName.$charId" : $eveLoginName,
                $requestHeaders,
                $charId,
            );
        }

        return $httpClient->sendRequest($request);
    }
}
