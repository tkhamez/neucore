<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neucore\Factory\HttpClientFactoryInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpClientFactory implements HttpClientFactoryInterface
{
    private \GuzzleHttp\Client $client;

    public function __construct(\GuzzleHttp\Client $client)
    {
        $this->client = $client;
    }

    public function get(?string $cacheKey = 'default'): ClientInterface
    {
        return $this->client;
    }

    public function getGuzzleClient(?string $cacheKey = 'default'): \GuzzleHttp\ClientInterface
    {
        return $this->client;
    }

    public function createRequest(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null
    ): RequestInterface {
        return new Request($method, $url, $headers, $body);
    }

    public function createResponse(
        int $status = 200,
        array $headers = [],
        ?string $body = null,
        ?string $reason = null
    ): ResponseInterface {
        return new Response($status, $headers, $body, '1.1', $reason);
    }
}
