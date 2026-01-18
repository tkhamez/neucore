<?php

declare(strict_types=1);

namespace Neucore\Factory;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheEntry;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Neucore\Middleware\Guzzle\EsiRateLimits;
use Neucore\Middleware\Guzzle\EsiErrorLimit;
use Neucore\Middleware\Guzzle\EsiThrottled;
use Neucore\Middleware\Guzzle\EsiWarnings;
use Neucore\Service\Config;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;

class HttpClientFactory implements HttpClientFactoryInterface
{
    public const HTTP_CLIENT_OPTIONS_KEY = 'x-neucore';

    public function __construct(
        private readonly Config $config,
        private readonly EsiErrorLimit $esiHeaders,
        private readonly EsiWarnings $esiWarnings,
        private readonly EsiRateLimits $esiRateLimits,
        private readonly EsiThrottled $esiThrottled,
        private readonly LoggerInterface $logger,
        private readonly Connection $dbalConnection,
    ) {}

    public function get(
        ?string $cacheKey = 'default',
        array $requestHeaders = [],
        ?int $characterId = null,
    ): ClientInterface {
        return $this->getClient($cacheKey, $requestHeaders, $characterId);
    }

    public function getGuzzleClient(
        ?string $cacheKey = 'default',
        array $requestHeaders = [],
        ?int $characterId = null,
    ): \GuzzleHttp\ClientInterface {
        return $this->getClient($cacheKey, $requestHeaders, $characterId);
    }

    public function createRequest(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
    ): RequestInterface {
        return new Request($method, $url, $headers, $body);
    }

    public function createResponse(
        int $status = 200,
        array $headers = [],
        ?string $body = null,
        ?string $reason = null,
    ): ResponseInterface {
        return new Response($status, $headers, $body, '1.1', $reason);
    }

    /**
     * @param array<string, string> $requestHeaders
     * @see \Neucore\Command\CleanHttpCache::execute()
     */
    private function getClient(
        ?string $cacheKey,
        array $requestHeaders = [],
        ?int $characterId = null,
    ): Client {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $debugFunc = function (MessageInterface $r): MessageInterface {
            if ($r instanceof RequestInterface) {
                $this->logger->debug($r->getMethod() . ' ' . $r->getUri());
            } elseif ($r instanceof ResponseInterface) {
                $this->logger->debug('Status Code: ' . $r->getStatusCode());
            }
            $headers = array_map(function ($val) {
                return $val[0];
            }, $r->getHeaders());
            $this->logger->debug($r->getBody()->getContents());
            $r->getBody()->rewind();
            $this->logger->debug(print_r($headers, true));
            return $r;
        };

        $stack = HandlerStack::create();
        #$stack->push(\GuzzleHttp\Middleware::mapRequest($debugFunc));

        if (!empty($cacheKey)) {
            $storage = $this->createStorage($cacheKey);
            $cache = new CacheMiddleware(new PrivateCacheStrategy($storage));
            $stack->push($cache, 'cache');
        }

        $stack->push($this->esiHeaders);
        $stack->push($this->esiWarnings);
        $stack->push($this->esiRateLimits);
        $stack->push($this->esiThrottled);
        #$stack->push(\GuzzleHttp\Middleware::mapResponse($debugFunc));

        return new Client([
            'version' => '1.1',
            CURLOPT_FORBID_REUSE => true,
            CURLOPT_FRESH_CONNECT => true,
            'handler' => $stack,
            'headers' => array_merge(
                $requestHeaders,
                [
                    'Connection' => 'close',
                    'User-Agent' => $this->config['guzzle']['user_agent'],
                ],
            ),
            self::HTTP_CLIENT_OPTIONS_KEY => [
                'character_id' => $characterId,
            ],
        ]);
    }

    private function createStorage(string $cacheKey): Psr6CacheStorage
    {
        $tableName = $this->config['guzzle']['cache']['table'];
        $adapter = new DoctrineDbalAdapter(
            $this->dbalConnection,
            $cacheKey,
            86400, // one day
            ['db_table' => $tableName],
        );
        $storage = new Psr6CacheStorage($adapter);

        // This creates the table if it does not exist yet.
        $storage->save('init', new CacheEntry(
            new Request('GET', 'https://example.com/'),
            new Response(200, [], 'test'),
            new \DateTime('+ 1 minute'),
        ));
        $adapter->commit();

        return $storage;
    }
}
