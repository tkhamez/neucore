<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\ClientInterface;
use Neucore\Factory\HttpClientFactoryInterface;

class HttpClientFactory implements HttpClientFactoryInterface
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function get(string $cacheKey = ''): ClientInterface
    {
        return $this->client;
    }
}
