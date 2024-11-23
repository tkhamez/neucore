<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BodyParams implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_starts_with($contentType, 'application/json')) {
            $body = $request->getBody()->__toString();
            $contents = json_decode($body);
            if (is_array($contents) || is_object($contents)) {
                $request = $request->withParsedBody($contents);
            }
        } elseif (str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
            if ($request->getMethod() !== 'POST') { // POST data is already handled
                parse_str($request->getBody()->__toString(), $contents);
                $request = $request->withParsedBody($contents);
            }
        }

        return $handler->handle($request);
    }
}
