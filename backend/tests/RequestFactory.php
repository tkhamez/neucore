<?php declare(strict_types=1);

namespace Tests;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

class RequestFactory
{
    public static function createRequest(string $method = 'GET', $uri = '/'): ServerRequestInterface
    {
        return (new ServerRequestFactory)->createServerRequest($method, $uri);
    }
}
