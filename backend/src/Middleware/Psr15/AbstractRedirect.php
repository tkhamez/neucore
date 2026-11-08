<?php

namespace Neucore\Middleware\Psr15;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Performs a redirect to its own URL so that the session cookie can use “SameSite=Strict”
 * if the original request came from an external URL.
 *
 * This must happen before the PHP session is started; a standard HTTP redirect header cannot be used.
 */
class AbstractRedirect
{
    private const REDIRECTED = 'redirected';

    protected function respond(
        ServerRequestInterface $request,
        ResponseFactoryInterface $responseFactory,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (($request->getQueryParams()[self::REDIRECTED] ?? null) !== '1') {
            $uri = $request->getUri();
            $path = $uri->getPath();
            $query = $uri->getQuery();
            $redirectUrl = "$path?$query" . ($query === '' ? '' : '&') . self::REDIRECTED . '=1';
            $response = $responseFactory->createResponse()->withHeader('Content-Type', 'text/html');
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
