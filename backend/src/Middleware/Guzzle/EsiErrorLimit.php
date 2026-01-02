<?php

declare(strict_types=1);

namespace Neucore\Middleware\Guzzle;

use Neucore\Storage\StorageDatabaseInterface;
use Neucore\Storage\Variables;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Updates ESI error limit
 */
class EsiErrorLimit
{
    public function __construct(private readonly StorageDatabaseInterface $storage) {}

    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function (ResponseInterface $response) {
                    $this->handleResponseHeaders($response);
                    return $response;
                },
            );
        };
    }

    private function handleResponseHeaders(ResponseInterface $response): void
    {
        if (
            $response->hasHeader('X-Esi-Error-Limit-Remain') &&
            $response->hasHeader('X-Esi-Error-Limit-Reset')
        ) {
            $remain = (int) $response->getHeader('X-Esi-Error-Limit-Remain')[0];
            $reset = (int) $response->getHeader('X-Esi-Error-Limit-Reset')[0];
            $this->storage->set(
                Variables::ESI_ERROR_LIMIT,
                (string) \json_encode(new \Neucore\Data\EsiErrorLimit(time(), $remain, $reset)),
            );
        }
    }
}
