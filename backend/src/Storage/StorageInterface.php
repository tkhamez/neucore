<?php

declare(strict_types=1);

namespace Neucore\Storage;

use Neucore\Exception\RuntimeException;

/**
 * A (preferably very fast) volatile memory shared by multiple requests.
 */
interface StorageInterface
{
    /**
     * @param string $key max. length = 112
     * @param string $value max. length = 255
     * @throws RuntimeException if parameters are too long
     * @return bool
     */
    public function set(string $key, string $value): bool;

    public function get(string $key): ?string;
}
