<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Issues a redirect for the OAuth login callback.
 */
class OAuthRedirect extends AbstractRedirect implements MiddlewareInterface
{
    public const LOGIN_CALLBACK = '/login-callback';

    public const LOGIN_CALLBACK_LEGACY = '/api/user/auth/callback'; // for backwards compatibility

    /**
     * @var string[]
     */
    private const CALLBACK_PATHS = [
        self::LOGIN_CALLBACK,
        self::LOGIN_CALLBACK_LEGACY, // only for backwards compatibility
    ];

    public function __construct(private readonly ResponseFactoryInterface $responseFactory) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!in_array($path, self::CALLBACK_PATHS, true)) {
            return $handler->handle($request);
        }

        return $this->respond($request, $this->responseFactory, $handler);
    }
}
