<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

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

class RateLimitApp extends RateLimit implements MiddlewareInterface
{
    private AppAuth $appAuth;

    private StorageInterface $storage;

    private ResponseFactoryInterface $responseFactory;

    private LoggerInterface $logger;

    private RepositoryFactory $repositoryFactory;

    private ?int $maxRequests = null;

    private ?int $resetTime = null;

    private bool $active = false;

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

        if (!$this->maxRequests || !$this->resetTime) {
            // Feature is not configured.
            return $handler->handle($request);
        }

        $key = Variables::API_RATE_LIMIT . '_' . $app->getId();
        list($remaining, $resetIn, $numRequests, $elapsedTime) =
            $this->checkLimit($key, $this->storage, $this->maxRequests, $this->resetTime);

        $response = null;
        if ($remaining < 0) {
            $this->logger->info(
                "API Rate Limit: App {$app->getId()} '{$app->getName()}', " .
                "limit exceeded with $numRequests request in $elapsedTime seconds."
            );
            if ($this->active) {
                $response = $this->responseFactory->createResponse(429); // Too Many Requests
                $response->getBody()->write(
                    "Application rate limit exceeded with $numRequests requests in $elapsedTime seconds."
                );
            }
        }
        if (!$response) {
            $response = $handler->handle($request);
        }

        if ($this->active) {
            $response = $this->addHeader($response, $remaining, $resetIn);
        }

        return $response;
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
        $this->active = $activeVar && $activeVar->getValue();
    }
}
