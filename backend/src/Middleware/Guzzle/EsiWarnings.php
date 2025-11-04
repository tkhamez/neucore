<?php

declare(strict_types=1);

namespace Neucore\Middleware\Guzzle;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Logs deprecated warnings
 */
class EsiWarnings
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $requestUri = $request->getUri()->__toString();

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($requestUri) {
                    $this->handleResponseHeaders($requestUri, $response);
                    return $response;
                },
            );
        };
    }

    private function handleResponseHeaders(string $requestUri, ResponseInterface $response): void
    {
        foreach (['warning', 'Warning'] as $headerName) {
            if ($response->hasHeader($headerName)) {
                $warning = $response->getHeader($headerName)[0];
                if (str_contains($warning, '299')) { // i.e. "299 - This route is deprecated"
                    $this->logger->warning("$requestUri: $warning");
                }
            }
        }
    }
}
