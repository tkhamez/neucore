<?php

namespace Neucore\Factory;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface HttpClientFactoryInterface
{
    public function get(?string $cacheKey = 'default'): ClientInterface;

    public function getGuzzleClient(?string $cacheKey = 'default'): \GuzzleHttp\ClientInterface;

    public function createRequest(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null
    ): RequestInterface;

    public function createResponse(
        int $status = 200,
        array $headers = [],
        ?string $body = null,
        ?string $reason = null
    ): ResponseInterface;
}
