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

        if (strpos($contentType, 'application/json') === 0) {
            $body = $request->getBody()->__toString();
            $contents = json_decode($body, true);
            if (is_array($contents)) {
                $request = $request->withParsedBody($contents);
            }
        } elseif (strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
            if ($request->getMethod() !== 'POST') { // POST data is already handled
                parse_str($request->getBody()->__toString(), $contents);
                if (is_array($contents)) {
                    $request = $request->withParsedBody($contents);
                }
            }
        }

        return $handler->handle($request);
    }
}
