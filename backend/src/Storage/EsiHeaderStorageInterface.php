<?php

declare(strict_types=1);

namespace Neucore\Storage;

use Neucore\Exception\RuntimeException;

/**
 * A (preferably very fast) volatile memory shared by multiple requests.
 */
interface EsiHeaderStorageInterface
{
    /**
     * @param string $key max. length = 112
     * @param string $value max. size approximately 16 MB
     * @throws RuntimeException if parameters are too long/big
     */
    public function set(string $key, string $value): bool;

    public function get(string $key): ?string;
}
