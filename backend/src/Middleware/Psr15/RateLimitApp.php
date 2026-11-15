<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Neucore\Entity\SystemVariable;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\Context;
use Neucore\Service\AppAuth;
use Neucore\Service\RateLimitState;
use Neucore\Storage\ApiRateLimitStorageInterface;
use Neucore\Storage\Variables;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class RateLimitApp extends RateLimit implements MiddlewareInterface
{
    private ?int $maxRequests = null;

    private ?int $resetTime = null;

    private bool $active = false;

    public function __construct(
        private readonly AppAuth $appAuth,
        private readonly ApiRateLimitStorageInterface $storage,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly LoggerInterface $logger,
        private readonly RepositoryFactory $repositoryFactory,
        RateLimitState $rateLimitState,
    ) {
        parent::__construct($rateLimitState);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $app = $this->appAuth->getApp($request);
        if ($app === null) {
            // Not a request from an authorised app.
            return $handler->handle($request);
        }

        $this->readConfig();

        if (!$this->maxRequests || !$this->resetTime) {
            // Feature is not configured.
            return $handler->handle($request);
        }

        $key = Variables::RATE_LIMIT_APP . '_' . $app->getId();
        try {
            [$remaining, $resetIn, $numRequests, $elapsedTime]
                = $this->checkLimit($key, $this->storage, $this->maxRequests, $this->resetTime);
        } catch (RuntimeException $e) {
            // The rate limit storage is not available, let the request pass without a rate limit (fail-open).
            $this->logger->error("API Rate Limit (app {$app->getId()}): {$e->getMessage()}", [
                Context::EXCEPTION => $e,
            ]);

            return $handler->handle($request);
        }

        $this->rateLimitState->setApp((int) $remaining, (float) $resetIn);

        $response = null;
        if ($remaining < 0) {
            $this->logger->info(
                "API Rate Limit: App {$app->getId()} '{$app->getName()}', "
                . "limit exceeded with $numRequests request in $elapsedTime seconds.",
            );
            if ($this->active) {
                $response = $this->responseFactory->createResponse(429); // Too Many Requests
                $response->getBody()->write(
                    "Application rate limit exceeded with $numRequests requests in $elapsedTime seconds.",
                );
            }
        }
        if (!$response) {
            $response = $handler->handle($request);
        }

        if ($this->active) {
            $response = $this->addHeader($response);
        }

        return $response;
    }

    private function readConfig(): void
    {
        if ($this->maxRequests !== null) {
            return;
        }

        $sysRepo = $this->repositoryFactory->getSystemVariableRepository();

        $maxRequestsVar = $sysRepo->find(SystemVariable::RATE_LIMIT_APP_MAX_REQUESTS);
        $resetTimeVar = $sysRepo->find(SystemVariable::RATE_LIMIT_APP_RESET_TIME);
        $activeVar = $sysRepo->find(SystemVariable::RATE_LIMIT_APP_ACTIVE);

        $this->maxRequests = $maxRequestsVar ? abs((int) $maxRequestsVar->getValue()) : 0;
        $this->resetTime = $resetTimeVar ? abs((int) $resetTimeVar->getValue()) : 0;
        $this->active = $activeVar && $activeVar->getValue();
    }
}
