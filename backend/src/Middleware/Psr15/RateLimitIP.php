<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Neucore\Exception\RuntimeException;
use Neucore\Log\Context;
use Neucore\Service\Config;
use Neucore\Service\RateLimitState;
use Neucore\Storage\Implementation\ApcuStorage;
use Neucore\Storage\Implementation\MemcachedStorage;
use Neucore\Storage\Interface\ApiRateLimitStorage;
use Neucore\Storage\Variables;
use Neucore\Util\Http;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class RateLimitIP extends RateLimit implements MiddlewareInterface
{
    /**
     * Only used in unit tests.
     */
    public static bool $active = true;

    public function __construct(
        private readonly ApiRateLimitStorage $storage,
        private readonly Config $config,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
        RateLimitState $rateLimitState,
    ) {
        parent::__construct($rateLimitState);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!self::$active) {
            return $handler->handle($request);
        }

        // Do not check the IP base rate limit if the database storage driver is used,
        // as it is slow and would need a database connection per request.
        if (!in_array(get_class($this->storage), [MemcachedStorage::class, ApcuStorage::class])) {
            return $handler->handle($request);
        }

        $maxRequests = (int) $this->config['rate_limit']['max'];
        $resetTime = (int) $this->config['rate_limit']['time'];
        if ($maxRequests === 0 || $resetTime === 0) {
            return $handler->handle($request);
        }

        $trustedProxies = [];
        $configured = $this->config['trusted_proxies'];
        if (is_string($configured) && $configured !== '') {
            $trustedProxies = array_filter(array_map('trim', explode(',', $configured)));
        }

        $ip = Http::ipAddress($trustedProxies);
        $key = Variables::RATE_LIMIT_IP . '_' . self::ipKey($ip);
        try {
            [$remaining, $resetIn, $numRequests, $elapsedTime]
                = $this->checkLimit($key, $this->storage, $maxRequests, $resetTime);
        } catch (RuntimeException $e) {
            // The rate limit storage is not available, let the request pass without a rate limit (fail-open).
            $this->logger->error("IP Rate Limit ($ip): {$e->getMessage()}", [
                Context::EXCEPTION => $e,
            ]);

            return $handler->handle($request);
        }

        $this->rateLimitState->setIp((int) $remaining, (float) $resetIn);

        if ($remaining < 0) {
            $appId = Http::appId();
            $appIdLog = empty($appId) ? '' : ", App-ID $appId";
            $this->logger->info(
                "IP Rate Limit: $ip$appIdLog, "
                . "limit exceeded with $numRequests request in $elapsedTime seconds.",
            );
            $response = $this->responseFactory->createResponse(429); // Too Many Requests
            $response->getBody()->write(
                "IP rate limit exceeded with $numRequests requests in $elapsedTime seconds.",
            );
        } else {
            $response = $handler->handle($request);
        }

        return $this->addHeader($response);
    }

    /**
     * Returns the unique, collision-free key component for an IP address.
     *
     * The binary (canonical) representation is used, so the key is the same for plain IPv4 and
     * IPv4-mapped IPv6 addresses and independent of the IPv6 compression format.
     */
    private static function ipKey(string $ip): string
    {
        $binary = Http::ipNormalizeBinary($ip);

        return $binary === false ? Http::IP_UNKNOWN : bin2hex($binary);
    }
}
