<?php

declare(strict_types=1);

namespace Neucore\Storage\Interface;

use Neucore\Exception\RuntimeException;
use Neucore\Storage\RateLimitCounter;

/**
 * A (preferably very fast) volatile memory shared by multiple requests.
 */
interface ApiRateLimitStorage
{
    /**
     * Atomically consume one request of the rate limit counter.
     *
     * The counter is created, or restarted if the time window has expired,
     * when it does not exist, otherwise the remaining count is decreased by one.
     *
     * @throws RuntimeException if $key is too long or the operation failed
     */
    public function consume(string $key, int $maxRequests, int $resetTime): RateLimitCounter;
}
