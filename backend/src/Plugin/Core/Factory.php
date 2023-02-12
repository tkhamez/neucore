<?php

declare(strict_types=1);

namespace Neucore\Plugin\Core;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Yaml\Parser;

class Factory implements FactoryInterface
{
    public function __construct(
        private EsiClient $esiClient,
        private Account $account,
    ) {
    }

    public function createHttpClient(string $userAgent = ''): ClientInterface
    {
        $headers = [];

        if (!empty($userAgent)) {
            $headers = [
                'User-Agent' => $userAgent,
            ];
        }

        return new Client([
            'headers' => $headers,
        ]);
    }

    public function createHttpRequest(
        string $method,
        string $url,
        array $headers = [],
        string $body = null
    ): RequestInterface {
        return new Request($method, $url, $headers, $body);
    }

    public function createSymfonyYamlParser(): Parser
    {
        return new Parser();
    }

    public function getEsiClient(): EsiClientInterface
    {
        return $this->esiClient;
    }

    public function getAccount(): AccountInterface
    {
        return $this->account;
    }
}
