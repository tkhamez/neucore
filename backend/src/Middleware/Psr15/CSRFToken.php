<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Neucore\Service\SessionData;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\RouteContext;

class CSRFToken implements MiddlewareInterface
{
    public const CSRF_HEADER_NAME = 'X-CSRF-Token';

    public const CSRF_SESSION_NAME = 'csrfToken';

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var SessionData
     */
    private $sessionData;

    /**
     * @var string
     */
    private $includeRoute;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        SessionData $sessionData,
        string $includeRoute
    ) {
        $this->responseFactory = $responseFactory;
        $this->sessionData = $sessionData;
        $this->includeRoute = $includeRoute;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // check if current route matches configuration
        $route = RouteContext::fromRequest($request)->getRoute();
        if ($route === null) {
            return $handler->handle($request);
        }
        if (strpos($route->getPattern(), $this->includeRoute) !== 0) {
            return $handler->handle($request);
        }

        // check token
        if (
            in_array($request->getMethod(), ['POST', 'PUT', 'DELETE']) &&
            (
                empty($this->sessionData->get(self::CSRF_SESSION_NAME)) ||
                !$request->hasHeader(self::CSRF_HEADER_NAME) ||
                $request->getHeader(self::CSRF_HEADER_NAME)[0] !== $this->sessionData->get(self::CSRF_SESSION_NAME)
            )
        ) {
            return $this->responseFactory->createResponse()->withStatus(403);
        }

        return $handler->handle($request);
    }
}
