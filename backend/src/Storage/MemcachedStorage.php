<?php

declare(strict_types=1);

namespace Neucore\Storage;

use Neucore\Exception\RuntimeException;
use Psr\Log\LoggerInterface;

class MemcachedStorage implements EsiHeaderStorageInterface, ApiRateLimitStoreInterface
{
    public const PREFIX = '__neucore__';

    /**
     * @noinspection PhpComposerExtensionStubsInspection
     */
    public function __construct(
        private readonly \Memcached $memcached,
        private readonly LoggerInterface $logger,
    ) {}

    public function set(string $key, string $value): bool
    {
        if (mb_strlen($key) > 112) {
            throw new RuntimeException('Key too long.');
        }

        $expiration = time() + 86400; // one day
        $value = $this->memcached->set(self::PREFIX . $key, $value, $expiration);

        if ($value === false) {
            $this->logger->error("MemcachedStorage: Failed to set '$key'.");
            return false;
        }

        return true;
    }

    public function get(string $key): ?string
    {
        $value = $this->memcached->get(self::PREFIX . $key);

        if ($value === false) {
            // "false" means either "key not found" (a normal state) or "server unavailable".
            /** @noinspection PhpComposerExtensionStubsInspection */
            if ($this->memcached->getResultCode() !== \Memcached::RES_NOTFOUND) {
                $this->logger->error("MemcachedStorage: Failed to get '$key'.");
            }
            return null;
        }

        return (string) $value;
    }

    public function consume(string $key, int $maxRequests, int $resetTime): RateLimitCounter
    {
        if (mb_strlen($key) > 112) {
            throw new RuntimeException('Key too long.');
        }

        $fullKey = self::PREFIX . $key;
        $expiration = time() + 86400; // one day
        $now = microtime(true);

        for ($attempts = 0; $attempts < 3; $attempts++) {
            // Try to create the counter, add() is atomic and only succeeds when the key does not exist.
            $counter = RateLimitCounter::create($maxRequests, $now);
            if ($this->memcached->add($fullKey, $counter->toJson(), $expiration)) {
                return $counter;
            }

            /** @noinspection PhpComposerExtensionStubsInspection */
            $result = $this->memcached->get($fullKey, null, \Memcached::GET_EXTENDED);
            if ($result === false) {
                // The key expired in the meantime, try to create it again
                continue;
            }
            if (!is_array($result)) {
                throw new RuntimeException('Failed to consume the rate limit counter (1).');
            }

            $counter = RateLimitCounter::next((string) $result['value'], $maxRequests, $resetTime, $now);

            if ($this->memcached->cas($result['cas'], $fullKey, $counter->toJson(), $expiration)) {
                return $counter;
            }

            // CAS conflict, another request wrote to the key in the meantime, retry
        }

        throw new RuntimeException('Failed to consume the rate limit counter (2).');
    }
}
