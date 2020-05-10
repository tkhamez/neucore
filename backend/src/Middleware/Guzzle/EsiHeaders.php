<?php

declare(strict_types=1);

namespace Neucore\Middleware\Guzzle;

use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class EsiHeaders
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var StorageInterface
     */
    private $storage;

    public function __construct(LoggerInterface $logger, StorageInterface $storage)
    {
        $this->logger = $logger;
        $this->storage = $storage;
    }

    /**
     * @param callable $handler
     * @return \Closure
     */
    public function __invoke(callable $handler)
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            $requestUri = $request->getUri()->__toString();

            return $handler($request, $options)->then(
                function (ResponseInterface $response) use ($requestUri) {
                    $this->handleResponseHeaders($requestUri, $response);
                    return $response;
                }
            );
        };
    }

    private function handleResponseHeaders(string $requestUri, ResponseInterface $response): void
    {
        /// update ESI error limit
        if ($response->hasHeader('X-Esi-Error-Limit-Remain') && $response->hasHeader('X-Esi-Error-Limit-Reset')) {
            $remain = (int) $response->getHeader('X-Esi-Error-Limit-Remain')[0];
            $reset = (int) $response->getHeader('X-Esi-Error-Limit-Reset')[0];
            $this->storage->set(Variables::ESI_ERROR_LIMIT, (string) \json_encode([
                'updated' => time(),
                'remain' => $remain,
                'reset' => $reset,
            ]));
        }

        // log deprecated warnings
        if ($response->hasHeader('warning')) {
            $warning = $response->getHeader('warning')[0];
            if (strpos($warning, '299') !== false) { // i. e. "299 - This route is deprecated"
                $this->logger->warning($requestUri . ': ' .$warning);
            }
        }
    }
}
