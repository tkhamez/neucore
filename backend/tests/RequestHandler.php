<?php

declare(strict_types=1);

namespace Tests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class RequestHandler implements RequestHandlerInterface
{
    private $request;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        return (new ResponseFactory())->createResponse();
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
