<?php

declare(strict_types=1);

namespace Neucore\Middleware\Guzzle;

use Neucore\Data\EsiErrorLimit;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class EsiHeaders
{
    private LoggerInterface $logger;

    private StorageInterface $storage;

    public function __construct(LoggerInterface $logger, StorageInterface $storage)
    {
        $this->logger = $logger;
        $this->storage = $storage;
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
        // Update ESI error limit
        if ($response->hasHeader('X-Esi-Error-Limit-Remain') && $response->hasHeader('X-Esi-Error-Limit-Reset')) {
            $remain = (int) $response->getHeader('X-Esi-Error-Limit-Remain')[0];
            $reset = (int) $response->getHeader('X-Esi-Error-Limit-Reset')[0];
            $this->storage->set(Variables::ESI_ERROR_LIMIT, (string) \json_encode(
                new EsiErrorLimit(time(), $remain, $reset),
            ));
        }

        // Log deprecated warnings
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
