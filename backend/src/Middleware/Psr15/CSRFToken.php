<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Neucore\Service\SessionData;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CSRFToken implements MiddlewareInterface
{
    const CSRF_HEADER_NAME = 'X-CSRF-Token';

    const CSRF_SESSION_NAME = 'csrfToken';

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var SessionData
     */
    private $sessionData;

    public function __construct(ResponseFactoryInterface $responseFactory, SessionData $sessionData)
    {
        $this->responseFactory = $responseFactory;
        $this->sessionData = $sessionData;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sessionValue = null;
        try {
            $sessionValue = $this->sessionData->get(self::CSRF_SESSION_NAME);
        } catch (\RuntimeException$e) {
            // session not started, e.g. API calls
            return $handler->handle($request);
        }

        if (
            in_array($request->getMethod(), ['POST', 'PUT', 'DELETE']) &&
            (
                empty($sessionValue) ||
                !$request->hasHeader(self::CSRF_HEADER_NAME) ||
                $request->getHeader(self::CSRF_HEADER_NAME)[0] !== $this->sessionData->get(self::CSRF_SESSION_NAME)
            )
        ) {
            return $this->responseFactory->createResponse()->withStatus(403);
        }

        return $handler->handle($request);
    }
}
