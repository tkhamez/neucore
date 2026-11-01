<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Issues a redirect for the OAuth login callback in a way that allows the session cookie to use SameSite=Strict.
 * This must happen before the PHP session is started; a standard HTTP redirect header cannot be used here.
 */
class OAuthRedirect implements MiddlewareInterface
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

        $queryParams = $request->getQueryParams();
        if (($queryParams['redirected'] ?? null) !== '1') {
            // Forward the original query string, so that no parameter is lost (e.g. error parameters).
            $query = $request->getUri()->getQuery();
            $redirectUrl = $path . '?' . $query . ($query === '' ? '' : '&') . 'redirected=1';
            $response = $this->responseFactory->createResponse()->withHeader('Content-Type', 'text/html');
            $response->getBody()->write(
                '<!DOCTYPE html>
				<meta http-equiv="refresh" content="0; url='
                    . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '">
				<title>Neucore</title>',
            );
            return $response;
        }

        return $handler->handle($request);
    }
}
