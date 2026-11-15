<?php

declare(strict_types=1);

namespace Neucore\Storage;

/**
 * The state of a rate limit counter, as stored by the ApiRateLimitStoreInterface storages.
 */
final class RateLimitCounter
{
    public function __construct(
        public readonly int $remaining,
        public readonly float $created,
    ) {}

    /**
     * Creates the counter for a new time window.
     */
    public static function create(int $maxRequests, float $now): self
    {
        return new self($maxRequests - 1, $now);
    }

    /**
     * Consumes one request of a stored counter.
     *
     * Returns a new counter with the remaining count decreased by one,
     * or a fresh counter when the stored value is missing, invalid
     * or the time window has expired.
     */
    public static function next(?string $stored, int $maxRequests, int $resetTime, float $now): self
    {
        $decoded = $stored === null ? null : \json_decode($stored, true);
        if (
            is_array($decoded)
            && is_int($decoded['remaining'] ?? null)
            && (is_int($decoded['created'] ?? null) || is_float($decoded['created'] ?? null))
        ) {
            $counter = new self($decoded['remaining'], (float) $decoded['created']);
            if (($counter->created + $resetTime) > $now) {
                return new self($counter->remaining - 1, $counter->created);
            }
        }

        return self::create($maxRequests, $now);
    }

    /**
     * The stored JSON representation (object format).
     */
    public function toJson(): string
    {
        return (string) \json_encode(['remaining' => $this->remaining, 'created' => $this->created]);
    }
}
