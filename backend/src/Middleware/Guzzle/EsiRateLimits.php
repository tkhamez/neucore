<?php

declare(strict_types=1);

namespace Neucore\Middleware\Guzzle;

use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class EsiRateLimits
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
        // SSO rate limit, see also
        // https://developers.eveonline.com/blog/article/sso-endpoint-deprecations-2
        if ($response->getStatusCode() === 429) {
            $waitUntil = time() + 60;
            if ($response->hasHeader('Retry-After')) {
                $retryAfter = $response->getHeader('Retry-After')[0];
                $this->logger->warning("EsiRateLimits Retry-After: $retryAfter");
                if (is_numeric($retryAfter)) {
                    // e.g.: 120
                    $waitUntil = time() + ceil((float) $retryAfter);
                } else {
                    // e.g.: Wed, 21 Oct 2015 07:28:00 GMT
                    $datetime = \DateTime::createFromFormat('D, d M Y H:i:s T', $retryAfter);
                    if ($datetime instanceof \DateTime) {
                        $waitUntil = $datetime->getTimestamp();
                    }
                }
            }
            $this->storage->set(Variables::ESI_RATE_LIMIT, (string) $waitUntil);
        }
    }
}
