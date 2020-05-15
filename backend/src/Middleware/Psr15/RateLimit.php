<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Neucore\Entity\App;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\AppAuth;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class RateLimit implements MiddlewareInterface
{
    public const HEADER_REMAIN = 'X-Neucore-Rate-Limit-Remain';

    public const HEADER_RESET = 'X-Neucore-Rate-Limit-Reset';

    /**
     * @var AppAuth
     */
    private $appAuth;

    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var int
     */
    private $maxRequests;

    /**
     * @var int
     */
    private $resetTime;

    /**
     * @var bool
     */
    private $active;

    public function __construct(
        AppAuth $appAuth,
        StorageInterface $storage,
        ResponseFactoryInterface $responseFactory,
        LoggerInterface $logger,
        RepositoryFactory $repositoryFactory
    ) {
        $this->appAuth = $appAuth;
        $this->storage = $storage;
        $this->responseFactory = $responseFactory;
        $this->logger = $logger;
        $this->repositoryFactory = $repositoryFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $app = $this->appAuth->getApp($request);
        if ($app === null) {
            // Not a request from an authorized app.
            return $handler->handle($request);
        }

        $this->readConfig();

        if ($this->maxRequests === 0 || $this->resetTime === 0) {
            // Feature is not configured.
            return $handler->handle($request);
        }

        list($remaining, $resetIn, $numRequests, $elapsedTime) = $this->checkLimit($app);

        if ($remaining < 0 && $this->active) {
            $this->logger->debug(
                "{$this->logPrefix($app)} limit exceeded with $numRequests request in $elapsedTime seconds."
            );
            return $this->responseFactory->createResponse(429) // Too Many Requests
                ->withHeader(self::HEADER_REMAIN, $remaining)
                ->withHeader(self::HEADER_RESET, $resetIn);
        }

        return $handler->handle($request)
            ->withHeader(self::HEADER_REMAIN, $remaining)
            ->withHeader(self::HEADER_RESET, $resetIn);
    }

    private function readConfig(): void
    {
        if ($this->maxRequests !== null) {
            return;
        }

        $sysRepo = $this->repositoryFactory->getSystemVariableRepository();

        $maxRequests = $sysRepo->find(SystemVariable::API_RATE_LIMIT_MAX_REQUESTS);
        $resetTime = $sysRepo->find(SystemVariable::API_RATE_LIMIT_RESET_TIME);
        $active = $sysRepo->find(SystemVariable::API_RATE_LIMIT_ACTIVE);

        $this->maxRequests = $maxRequests ? abs((int) $maxRequests->getValue()) : 0;
        $this->resetTime = $resetTime ? abs((int) $resetTime->getValue()) : 0;
        $this->active = $active ? (bool) $active->getValue() : false;
    }

    private function checkLimit(App $app): array
    {
        $key = Variables::API_RATE_LIMIT . '_' . $app->getId();

        $value = $this->storage->get($key);
        $variable = $value ? \json_decode($value) : null;
        if ($variable === null) {
            $variable = new \stdClass();
            $variable->remaining = $this->maxRequests - 1;
            $variable->created = microtime(true);
        } else {
            $variable->remaining --;
        }

        $resetIn = ceil(($variable->created + $this->resetTime - microtime(true)) * 10) / 10; // header value
        $numRequests = $this->maxRequests - $variable->remaining;
        $elapsedTime = round(microtime(true) - $variable->created, 1); // log value

        if ($resetIn <= 0) {
            $this->logger->debug(
                "{$this->logPrefix($app)} $numRequests requests in $elapsedTime seconds."
            );
            $variable->remaining = $this->maxRequests - 1;
            $variable->created = microtime(true);
            $resetIn = $this->resetTime;
        }

        $this->storage->set($key, (string) \json_encode($variable));

        return [$variable->remaining, sprintf("%.1F", $resetIn), $numRequests, $elapsedTime];
    }

    private function logPrefix(App $app): string
    {
        return "API Rate Limit: App {$app->getId()} '{$app->getName()}',";
    }
}
