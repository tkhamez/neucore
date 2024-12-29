<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Neucore\Storage\StorageInterface;
use Psr\Http\Message\ResponseInterface;

abstract class RateLimit
{
    public const HEADER_REMAIN = 'X-Neucore-Rate-Limit-Remain';

    public const HEADER_RESET = 'X-Neucore-Rate-Limit-Reset';

    protected function checkLimit(string $key, StorageInterface $storage, int $maxRequests, int $resetTime): array
    {
        $value = $storage->get($key);
        $variable = $value ? \json_decode($value) : null;
        if ($variable === null) {
            $variable = new \stdClass();
            $variable->remaining = $maxRequests - 1;
            $variable->created = microtime(true);
        } else {
            $variable->remaining--;
        }

        $resetIn = ceil(($variable->created + $resetTime - microtime(true)) * 10) / 10; // HTTP response header value
        $numRequests = $maxRequests - $variable->remaining;
        $elapsedTime = round(microtime(true) - $variable->created, 1); // log value

        if ($resetIn <= 0) {
            $variable->remaining = $maxRequests - 1;
            $variable->created = microtime(true);
            $resetIn = $resetTime;
        }

        $storage->set($key, (string) \json_encode($variable));

        return [$variable->remaining, (float) sprintf("%.1F", $resetIn), $numRequests, $elapsedTime];
    }

    protected function addHeader(ResponseInterface $response, int $newRemaining, float $newResetIn): ResponseInterface
    {
        $remaining = $newRemaining;
        $resetIn = $newResetIn;

        $previousRemain = $response->getHeader(self::HEADER_REMAIN)[0] ?? null;
        $previousResetIn = $response->getHeader(self::HEADER_RESET)[0] ?? null;
        if ($previousRemain !== null && $previousResetIn !== null && $newRemaining > $previousRemain) {
            $remaining = $previousRemain;
            $resetIn = $previousResetIn;
        }

        return $response
            ->withHeader(self::HEADER_REMAIN, (string) $remaining)
            ->withHeader(self::HEADER_RESET, (string) $resetIn);
    }
}
