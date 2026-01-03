<?php

declare(strict_types=1);

namespace Neucore\Middleware\Guzzle;

use Neucore\Service\EsiClient;
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
            $response->hasHeader(EsiClient::HEADER_ERROR_LIMIT_REMAIN) &&
            $response->hasHeader(EsiClient::HEADER_ERROR_LIMIT_RESET)
        ) {
            $remain = (int) $response->getHeader(EsiClient::HEADER_ERROR_LIMIT_REMAIN)[0];
            $reset = (int) $response->getHeader(EsiClient::HEADER_ERROR_LIMIT_RESET)[0];
            $this->storage->set(
                Variables::ESI_ERROR_LIMIT,
                (string) \json_encode(new \Neucore\Data\EsiErrorLimit(time(), $remain, $reset)),
            );
        }
    }
}
