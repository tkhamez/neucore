<?php

namespace Neucore\Factory;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;

class ResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        if ($reasonPhrase !== '') {
            return (new Response($code))->withStatus($code, $reasonPhrase);
        }
        return new Response($code);
    }
}
