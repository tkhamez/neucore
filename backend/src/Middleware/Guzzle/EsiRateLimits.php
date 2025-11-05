<?php

declare(strict_types=1);

namespace Neucore\Middleware\Guzzle;

use Neucore\Data\EsiRateLimit;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles headers related to ESI rate limits.
 *
 * For SSO rate limit see https://developers.eveonline.com/blog/article/sso-endpoint-deprecations-2.
 * For ESI rate limit see https://developers.eveonline.com/docs/services/esi/rate-limiting/.
 */
class EsiRateLimits
{
    private LoggerInterface $logger;

    private StorageInterface $storage;

    public function __construct(LoggerInterface $logger, StorageInterface $storage)
    {
        $this->logger = $logger;
        $this->storage = $storage;
    }

    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function (ResponseInterface $response) {
                    $this->handleResponseHeaders($response);
                    return $response;
                },
            );
        };
    }

    private function handleResponseHeaders(ResponseInterface $response): void
    {
        if ($response->hasHeader('X-Ratelimit-Group')) {
            $rateLimits = EsiRateLimit::fromJson((string) $this->storage->get(Variables::ESI_RATE_LIMIT));

            $group = $response->getHeader('X-Ratelimit-Group')[0];
            $rateLimits[$group] = new EsiRateLimit(
                $response->getHeader('X-Ratelimit-Limit')[0] ?? '',
                (int) ($response->getHeader('X-Ratelimit-Remaining')[0] ?? -1),
                (int) ($response->getHeader('X-Ratelimit-Used')[0] ?? -1),
            );

            $this->storage->set(
                Variables::ESI_RATE_LIMIT,
                EsiRateLimit::toJson($rateLimits),
            );
        }

        if ($response->getStatusCode() === 429) {
            $waitUntil = time() + 60;
            if ($response->hasHeader('Retry-After')) {
                $retryAfter = $response->getHeader('Retry-After')[0];
                $this->logger->warning("EsiRateLimits Retry-After: $retryAfter");
                if (is_numeric($retryAfter)) { // number of seconds to wait
                    $waitUntil = time() + ceil((float) $retryAfter);
                } else {
                    // e.g.: Wed, 21 Oct 2015 07:28:00 GMT
                    $datetime = \DateTime::createFromFormat('D, d M Y H:i:s T', $retryAfter);
                    if ($datetime instanceof \DateTime) {
                        $waitUntil = $datetime->getTimestamp();
                    }
                }
            }
            $this->storage->set(Variables::ESI_RATE_LIMITED, (string) $waitUntil);
        }
    }
}
