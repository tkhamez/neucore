<?php

namespace Neucore\Factory;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface HttpClientFactoryInterface
{
    /**
     * @param ?string $cacheKey Optional subdirectory for file system cache (defaults to "default") or null
     *         to disable cache.
     * @param array<string, string> $requestHeaders
     */
    public function get(?string $cacheKey = 'default', array $requestHeaders = []): ClientInterface;

    /**
     * @param ?string $cacheKey Optional subdirectory for file system cache (defaults to "default") or null
     *         to disable cache.
     * @param array<string, string> $requestHeaders
     */
    public function getGuzzleClient(
        ?string $cacheKey = 'default',
        array $requestHeaders = [],
    ): \GuzzleHttp\ClientInterface;

    public function createRequest(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
    ): RequestInterface;

    public function createResponse(
        int $status = 200,
        array $headers = [],
        ?string $body = null,
        ?string $reason = null,
    ): ResponseInterface;
}
