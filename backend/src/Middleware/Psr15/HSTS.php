<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HSTS implements MiddlewareInterface
{
    private $maxAge;

    public function __construct(int $maxAge)
    {
        $this->maxAge = $maxAge;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withHeader('Strict-Transport-Security', "max-age={$this->maxAge}");
    }
}
