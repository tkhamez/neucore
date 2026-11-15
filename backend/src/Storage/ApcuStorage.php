<?php

namespace Neucore\Storage;

use Neucore\Exception\RuntimeException;

class ApcuStorage implements EsiHeaderStorageInterface, ApiRateLimitStorageInterface
{
    public const PREFIX = '__neucore__';

    public function set(string $key, string $value): bool
    {
        if (mb_strlen($key) > 112) {
            throw new RuntimeException('Key too long.');
        }

        /** @noinspection PhpComposerExtensionStubsInspection */
        return (bool) apcu_store(self::PREFIX . $key, $value);
    }

    public function get(string $key): ?string
    {
        /** @noinspection PhpComposerExtensionStubsInspection */
        $value = apcu_fetch(self::PREFIX . $key);

        if ($value === false) {
            return null;
        }

        return (string) $value;
    }

    public function consume(string $key, int $maxRequests, int $resetTime): RateLimitCounter
    {
        if (mb_strlen($key) > 112) {
            throw new RuntimeException('Key too long.');
        }

        // APCu is process-local and a process handles only one request at a time,
        // so the read-modify-write is race-free without a compare-and-swap.
        $now = microtime(true);
        $counter = RateLimitCounter::next($this->get($key), $maxRequests, $resetTime, $now);

        $this->set($key, $counter->toJson());

        return $counter;
    }
}
