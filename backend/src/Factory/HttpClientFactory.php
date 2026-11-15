<?php

declare(strict_types=1);

namespace Neucore\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class HttpClientFactory implements HttpClientFactoryInterface
{
    public const HTTP_CLIENT_OPTIONS_KEY = 'x-neucore';

    /**
     * @var callable(RequestInterface, array<string, mixed>): PromiseInterface|null Underlying
     *     HTTP handler. Injected in tests to mock the network; null uses the default cURL handler.
     */
    private $handler;

    public function __construct(
        private readonly Config $config,
        private readonly EsiErrorLimit $esiHeaders,
        private readonly EsiWarnings $esiWarnings,
        private readonly EsiRateLimits $esiRateLimits,
        private readonly EsiThrottled $esiThrottled,
        private readonly LoggerInterface $logger,
        private readonly int $retryDelay = 300,
        ?callable $handler = null,
    ) {
        $this->handler = $handler;
    }

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

        $stack = HandlerStack::create($this->handler);
        #$stack->push(\GuzzleHttp\Middleware::mapRequest($debugFunc));

        if (!empty($cacheKey)) {
            $storage = $this->createStorage($cacheKey, $characterId);
            if ($storage) {
                $cache = new CacheMiddleware(new PrivateCacheStrategy($storage));
                $stack->push($cache, 'cache');
            }
        }

        $retryDelay = $this->retryDelay;
        $logger = $this->logger;
        $retryMiddleware = Middleware::retry(
            function (
                int $retries,
                RequestInterface $request,
                ?ResponseInterface $response,
                ?GuzzleException $exception,
            ) {
                // This is called for every request.
                // $retries is the number of retries that were made so far.

                // Limit the number of retries to 2
                if ($retries >= 2) {
                    return false;
                }

                // Retry on connection errors - there's no response in this case
                if ($exception instanceof ConnectException) {
                    return true;
                }

                // Retry on server errors (5xx). sendRequest() (PSR-18) passes the 5xx response
                // directly, while for send()/request() the "http_errors" middleware below turns
                // it into an exception, so in that case the response must be derived from the
                // exception.
                if ($response === null && $exception instanceof RequestException) {
                    $response = $exception->getResponse();
                }
                if ($response && $response->getStatusCode() >= 500) {
                    return true;
                }

                return false;
            },
            function (int $retries, ?ResponseInterface $response, RequestInterface $request) use ($retryDelay, $logger) {
                // This is called before retrying.
                // $retries is the number of retries that were made so far plus one.

                $logger->debug("Retrying ($retries) {$request->getUri()}");

                // Wait (in milliseconds) before retrying
                return $retryDelay * $retries;
            },
        );
        $stack->push($retryMiddleware);

        $stack->push($this->esiHeaders);
        $stack->push($this->esiWarnings);
        $stack->push($this->esiRateLimits);
        $stack->push($this->esiThrottled);
        #$stack->push(\GuzzleHttp\Middleware::mapResponse($debugFunc));

        return new Client([
            'version' => '1.1',
            'connect_timeout' => (int) $this->config['guzzle']['connect_timeout'],
            'timeout' => (int) $this->config['guzzle']['timeout'],
            // None of the endpoints this client talks to (ESI, EVE API, SSO) are expected to
            // redirect, so a redirect is a sign of a hijacked endpoint or misconfigured proxy.
            // sendRequest() (PSR-18) already forces this to false; this covers send()/request().
            'allow_redirects' => false,
            // Make the default explicit: 4xx/5xx throw an exception for send()/request()
            // (EVE API, SSO). sendRequest() (PSR-18) forces this to false per request, so ESI
            // error responses are returned as responses and handled by the caller.
            'http_errors' => true,
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

    private function createStorage(string $cacheKey, ?int $characterId): ?Psr6CacheStorage
    {
        $namespace = '';
        $dir = $this->config['guzzle']['cache']['dir'];
        if ($characterId && str_ends_with($cacheKey, "$characterId")) {
            $subdirectory = substr($cacheKey, 0, -(strlen("$characterId") + 1));
            $namespace = "$characterId";
            $dir = $dir . DIRECTORY_SEPARATOR . $subdirectory;
        } else {
            $dir = $dir . DIRECTORY_SEPARATOR . $cacheKey;
        }
        $dirExists = is_dir($dir);
        if (!$dirExists && @mkdir($dir, 0775, true)) {
            $dirExists = true;
        }
        if ($dirExists && is_writable($dir)) {
            $lifetime = 86400; // one day
            $adapter = new FilesystemAdapter($namespace, $lifetime, $dir);
        } else {
            $this->logger->error("$dir is not writable or does not exist.");
            return null;
        }

        return new Psr6CacheStorage($adapter);
    }
}
