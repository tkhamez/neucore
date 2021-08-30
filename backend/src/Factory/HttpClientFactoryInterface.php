<?php

namespace Neucore\Factory;

use GuzzleHttp\ClientInterface;

interface HttpClientFactoryInterface
{
    public function get(string $cacheKey = ''): ClientInterface;
}
