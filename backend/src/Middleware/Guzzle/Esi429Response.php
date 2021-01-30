<?php

declare(strict_types=1);

namespace Neucore\Middleware\Guzzle;

use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Esi429Response
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

    public function __invoke(callable $handler): \Closure
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            return $handler($request, $options)->then(
                function (ResponseInterface $response) {
                    $this->handleResponseHeaders($response);
                    return $response;
                }
            );
        };
    }

    private function handleResponseHeaders(ResponseInterface $response): void
    {
        if ($response->getStatusCode() === 500) {
            $body = $response->getBody()->__toString();
            if (
                strpos($body, 'Undefined 429 response.') !== false &&
                strpos($body, 'Original message:') !== false &&
                strpos($body, 'Too many errors.') !== false &&
                strpos($body, 'You have been temporarily throttled.') !== false
            ) {
                $this->storage->set(Variables::ESI_THROTTLED, '1');
                return;
            }
        }
        $this->storage->set(Variables::ESI_THROTTLED, '0');
    }
}
