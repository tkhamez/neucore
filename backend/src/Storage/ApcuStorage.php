<?php

namespace Neucore\Storage;

use Neucore\Exception\RuntimeException;

class ApcuStorage implements StorageInterface
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
}
