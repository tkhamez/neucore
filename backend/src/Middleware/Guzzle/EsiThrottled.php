<?php

declare(strict_types=1);

namespace Neucore\Middleware\Guzzle;

use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * See https://github.com/esi/esi-issues/issues/1227
 */
class EsiThrottled
{
    public function __construct(private readonly StorageInterface $storage)
    {
    }

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
        if ($response->getStatusCode() === 500) {
            $body = $response->getBody()->__toString();
            if (
                str_contains($body, 'Undefined 429 response.') &&
                str_contains($body, 'Original message:') &&
                str_contains($body, 'Too many errors.') &&
                str_contains($body, 'You have been temporarily throttled.')
            ) {
                $this->storage->set(Variables::ESI_THROTTLED, (string) (time() + 60));
            }
        }
    }
}
