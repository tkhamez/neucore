<?php

declare(strict_types=1);

namespace Neucore\Factory;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Neucore\Middleware\Guzzle\Esi429Response;
use Neucore\Middleware\Guzzle\EsiHeaders;
use Neucore\Service\Config;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class HttpClientFactory implements HttpClientFactoryInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var EsiHeaders
     */
    private $esiHeaders;

    /**
     * @var Esi429Response
     */
    private $esi429Response;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        EsiHeaders $esiHeaders,
        Esi429Response $esi429Response,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->esiHeaders = $esiHeaders;
        $this->esi429Response = $esi429Response;
        $this->logger = $logger;
    }

    /**
     * @param string $cacheKey Optional subdirectory for file system cache, defaults to "default"
     * @see \Neucore\Command\CleanHttpCache::execute()
     */
    public function get(string $cacheKey = ''): ClientInterface
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $debugFunc = function (MessageInterface $r) {
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

        $cacheKey = empty($cacheKey) ? 'default' : $cacheKey;
        $cache = new CacheMiddleware(new PrivateCacheStrategy(new Psr6CacheStorage(
            // 86400 = one day lifetime
            new FilesystemAdapter('', 86400, $this->config['guzzle']['cache']['dir'] . DIRECTORY_SEPARATOR . $cacheKey)
        )));
        /* @phan-suppress-next-line PhanTypeMismatchArgument */
        $stack->push($cache, 'cache');

        $stack->push($this->esiHeaders);
        $stack->push($this->esi429Response);
        #$stack->push(\GuzzleHttp\Middleware::mapResponse($debugFunc));

        return new Client([
            'handler' => $stack,
            'headers' => [
                'User-Agent' => $this->config['guzzle']['user_agent'],
            ],
        ]);
    }
}
