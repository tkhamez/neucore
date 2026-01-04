<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Application;
use Neucore\Command\GenerateEveApiFiles;
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
    public const HEADER_ERROR_LIMIT_REMAIN = 'X-Esi-Error-Limit-Remain';

    public const HEADER_ERROR_LIMIT_RESET = 'X-Esi-Error-Limit-Reset';

    public const HEADER_RATE_LIMIT_GROUP = 'X-Ratelimit-Group';

    public const HEADER_RATE_LIMIT_LIMIT = 'X-Ratelimit-Limit';

    public const HEADER_RATE_LIMIT_REMAINING = 'X-Ratelimit-Remaining';

    public const HEADER_RATE_LIMIT_USED = 'X-Ratelimit-Used';

    /**
     * @see GenerateEveApiFiles::replacePlaceholders()
     */
    private const PATH_PATTERN = "@^{REG_EXP}(/)?([?].*)?$@";

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
     * Returns the earliest time at which another attempt can be made.
     *
     * Due to different values for $limitRemainPercent, the time may be later.
     *
     * Returns 0 if the limit has not yet been reached.
     */
    public static function getRateLimitWaitTime(
        StorageDatabaseInterface $storage,
        string $pathQuery,
        string $httpMethod,
        ?int $characterId,
        int $limitRemainPercent,
    ): int {
        $group = self::getRateLimitGroup($pathQuery, $httpMethod);
        foreach (self::getRateLimits($storage) as $bucket => $limit) {
            if (
                ($characterId === null && $bucket === $group) ||
                ($characterId !== null && $bucket === "$group:$characterId")
            ) {
                $wait = self::calculateRateLimitWaitTime($limit, $limitRemainPercent);
                if ($wait > 0) {
                    return time() + $wait;
                }
            }
        }

        return 0;
    }

    /**
     *
     * We only have the time of the last request, so it is unknown when tokens will be returned.
     * This calculation is based on the assumption that the tokens were used evenly across the window.
     * Then the result is doubled and another 10 seconds are added.
     *
     * @return int Number of seconds to wait.
     * @see https://developers.eveonline.com/docs/services/esi/rate-limiting/
     */
    public static function calculateRateLimitWaitTime(EsiRateLimit $limit, int $limitRemainPercent): int
    {
        $windowSeconds = $limit->getWindowInSeconds();
        $numTokens = $limit->getTokensPerWindow();
        if ($windowSeconds === 0 || $numTokens === 0) { // That shouldn't happen.
            return 0;
        }

        $wait = 0;
        $tokensShouldRemain = floor($numTokens / 100 * $limitRemainPercent);
        if ($limit->r < $tokensShouldRemain) {
            $tokensPerSecond = $numTokens / $windowSeconds;
            $minWaitSeconds = (($limit->u / $tokensPerSecond) * 2) + 10;
            $secondsSinceLastRequest = time() - $limit->t;
            $wait = (int) ceil(max(0, $minWaitSeconds - $secondsSinceLastRequest));
        }

        // Do not wait longer than the window duration.
        return min($wait, $windowSeconds);
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

    public static function isPublicPath(string $esiPathQuery): bool
    {
        $path = $esiPathQuery;
        if (
            str_starts_with($esiPathQuery, '/latest/') ||
            preg_match("@^/v([0-9])+/@", $esiPathQuery) === 1
        ) {
            // Strip the version from the old paths.
            $path = substr($esiPathQuery, (int) strpos($esiPathQuery, '/', 1));
        }

        $publicPaths = Application::loadFile('esi-paths-public.php');

        foreach ($publicPaths as $regExp) {
            $pattern = str_replace('{REG_EXP}', $regExp, self::PATH_PATTERN);
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function getRateLimitGroup(string $pathQuery, string $httpMethod): ?string
    {
        $rateLimits = Application::loadFile('esi-rate-limits.php');

        foreach ($rateLimits as $regExp => $data) {
            $pattern = str_replace('{REG_EXP}', $regExp, self::PATH_PATTERN);
            if (preg_match($pattern, $pathQuery) === 1) {
                if (isset($data[strtolower($httpMethod)])) {
                    return $data[strtolower($httpMethod)]['group'];
                }
            }
        }

        return null;
    }

    /**
     * @throws RuntimeException If character or a valid token could not be found.
     * @throws ClientExceptionInterface On request error.
     */
    public function request(
        string $esiPathQuery,
        string $method,
        ?string $body,
        ?int $characterId,
        string $eveLoginName = EveLogin::NAME_DEFAULT,
        bool $debug = false,
        ?string $compatibilityDate = null,
        ?string $acceptLanguage = null,
    ): ResponseInterface {
        $isPublicPath = self::isPublicPath($esiPathQuery);

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

        $url = $this->config['eve']['esi_host'] . $esiPathQuery;
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
