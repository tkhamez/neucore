<?php declare(strict_types=1);

namespace Tests;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Environment;
use Slim\Http\Request;

class RequestFactory
{
    public static function createRequest(array $userData = []): ServerRequestInterface
    {
        return Request::createFromEnvironment(Environment::mock($userData));
    }
}
