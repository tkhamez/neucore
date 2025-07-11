<?php

declare(strict_types=1);

namespace Neucore\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Neucore\Middleware\Guzzle\Esi429Response;
use Neucore\Middleware\Guzzle\EsiHeaders;
use Neucore\Service\Config;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class HttpClientFactory implements HttpClientFactoryInterface
{
    private Config $config;

    private EsiHeaders $esiHeaders;

    private Esi429Response $esi429Response;

    private LoggerInterface $logger;

    public function __construct(
        Config $config,
        EsiHeaders $esiHeaders,
        Esi429Response $esi429Response,
        LoggerInterface $logger,
    ) {
        $this->config = $config;
        $this->esiHeaders = $esiHeaders;
        $this->esi429Response = $esi429Response;
        $this->logger = $logger;
    }

    public function get(?string $cacheKey = 'default', array $requestHeaders = []): ClientInterface
    {
        return $this->getClient($cacheKey, $requestHeaders);
    }

    public function getGuzzleClient(
        ?string $cacheKey = 'default',
        array $requestHeaders = [],
    ): \GuzzleHttp\ClientInterface
    {
        return $this->getClient($cacheKey, $requestHeaders);
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
     * @see \Neucore\Command\CleanHttpCache::execute()
     */
    private function getClient(?string $cacheKey, array $requestHeaders = []): Client
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $debugFunc = function (MessageInterface $r): MessageInterface {
            if ($r instanceof RequestInterface) {
                $this->logger->debug($r->getMethod() . ' ' . $r->getUri());
            } elseif ($r instanceof ResponseInterface) {
                $this->logger->debug('Status Code: ' . $r->getStatusCode());
            }
            $headers = [];
            foreach ($r->getHeaders() as $name => $val) {
                $headers[$name] = $val[0];
            }
            $this->logger->debug($r->getBody()->getContents());
            $this->logger->debug(print_r($headers, true));
            return $r;
        };

        $stack = HandlerStack::create();
        #$stack->push(\GuzzleHttp\Middleware::mapRequest($debugFunc));

        if (!empty($cacheKey)) {
            $dir = $this->config['guzzle']['cache']['dir'] . DIRECTORY_SEPARATOR . $cacheKey;
            $dirExists = is_dir($dir);
            if (!$dirExists && @mkdir($dir, 0775, true)) {
                $dirExists = true;
            }
            if ($dirExists && is_writable($dir)) {
                $cache = new CacheMiddleware(new PrivateCacheStrategy(new Psr6CacheStorage(
                    // 86400 = one day lifetime
                    new FilesystemAdapter('', 86400, $dir),
                )));
                $stack->push($cache, 'cache');
            } else {
                $this->logger->error("$dir is not writable or does not exist.");
            }
        }

        $stack->push($this->esiHeaders);
        $stack->push($this->esi429Response);
        #$stack->push(\GuzzleHttp\Middleware::mapResponse($debugFunc));

        return new Client([
            'handler' => $stack,
            'headers' => array_merge(
                $requestHeaders,
                [
                    'User-Agent' => $this->config['guzzle']['user_agent'],
                ]
            ),
        ]);
    }
}
