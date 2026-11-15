<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Neucore\Exception\RuntimeException;
use Neucore\Service\RateLimitState;
use Neucore\Storage\ApiRateLimitStoreInterface;
use Psr\Http\Message\ResponseInterface;

abstract class RateLimit
{
    public const HEADER_REMAIN = 'X-Neucore-Rate-Limit-Remain';

    public const HEADER_RESET = 'X-Neucore-Rate-Limit-Reset';

    public function __construct(
        protected readonly RateLimitState $rateLimitState,
    ) {}

    /**
     * @return array{int, float, int, float}
     * @throws RuntimeException if the operation failed
     */
    protected function checkLimit(
        string $key,
        ApiRateLimitStoreInterface $storage,
        int $maxRequests,
        int $resetTime,
    ): array {
        $counter = $storage->consume($key, $maxRequests, $resetTime);

        $now = microtime(true);
        $resetIn = ceil(($counter->created + $resetTime - $now) * 10) / 10; // HTTP response header value
        $numRequests = $maxRequests - $counter->remaining;
        $elapsedTime = round($now - $counter->created, 1); // log value

        return [$counter->remaining, (float) sprintf("%.1F", $resetIn), $numRequests, $elapsedTime];
    }

    protected function addHeader(ResponseInterface $response): ResponseInterface
    {
        $state = $this->rateLimitState->get();

        return $response
            ->withHeader(self::HEADER_REMAIN, (string) ($state['remain'] ?? 0))
            ->withHeader(self::HEADER_RESET, (string) ($state['reset'] ?? 0.0));
    }
}
