<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS headers.
 *
 * Checks the HTTP_ORIGIN request header and, if it matches one of the allowed
 * origins, adds Access-Control-Allow-* headers to the response.
 */
class Cors implements MiddlewareInterface
{
    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var array
     */
    private $allowOrigin;

    /**
     * @param array $allowOrigin Example: ['https://frontend.domain.tld']
     */
    public function __construct(ResponseFactoryInterface $responseFactory, array $allowOrigin)
    {
        $this->responseFactory = $responseFactory;
        $this->allowOrigin = $allowOrigin;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = $this->responseFactory->createResponse();
        } else {
            $response = $handler->handle($request);
        }

        return $this->addHeader($request, $response);
    }

    private function addHeader(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->getHeader('HTTP_ORIGIN')[0] ?? null;
        if ($origin !== null && in_array($origin, $this->allowOrigin)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                #->withHeader('Access-Control-Allow-Headers', 'Authorization')
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    [CSRFToken::CSRF_HEADER_NAME, 'Content-Type', 'User-Agent']
                )
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ;
        }

        return $response;
    }
}
