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

        $response = null;
        if ($remaining < 0) {
            $this->logger->info(
                "{$this->logPrefix($app)} limit exceeded with $numRequests request in $elapsedTime seconds."
            );
            if ($this->active) {
                $response = $this->responseFactory->createResponse(429); // Too Many Requests
            }
        }
        if (!$response) {
            $response = $handler->handle($request);
        }

        return $response
            ->withHeader(self::HEADER_REMAIN, $remaining)
            ->withHeader(self::HEADER_RESET, $resetIn);
    }

    private function readConfig(): void
    {
        if ($this->maxRequests !== null) {
            return;
        }

        $sysRepo = $this->repositoryFactory->getSystemVariableRepository();

        $maxRequestsVar = $sysRepo->find(SystemVariable::API_RATE_LIMIT_MAX_REQUESTS);
        $resetTimeVar = $sysRepo->find(SystemVariable::API_RATE_LIMIT_RESET_TIME);
        $activeVar = $sysRepo->find(SystemVariable::API_RATE_LIMIT_ACTIVE);

        $this->maxRequests = $maxRequestsVar ? abs((int) $maxRequestsVar->getValue()) : 0;
        $this->resetTime = $resetTimeVar ? abs((int) $resetTimeVar->getValue()) : 0;
        $this->active = $activeVar ? (bool) $activeVar->getValue() : false;
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
