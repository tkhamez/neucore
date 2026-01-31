<?php

declare(strict_types=1);

namespace Neucore\Storage;

use Neucore\Exception\RuntimeException;

class MemcachedStorage implements EsiHeaderStorageInterface
{
    public const PREFIX = '__neucore__';

    /**
     * @noinspection PhpComposerExtensionStubsInspection
     */
    public function __construct(private readonly \Memcached $memcached)
    {
    }

    public function set(string $key, string $value): bool
    {
        if (mb_strlen($key) > 112) {
            throw new RuntimeException('Key too long.');
        }

        $expiration = time() + 86400; // one day

        return $this->memcached->set(self::PREFIX . $key, $value, $expiration);
    }

    public function get(string $key): ?string
    {
        $value = $this->memcached->get(self::PREFIX . $key);

        return $value === false ? null : (string) $value;
    }
}
