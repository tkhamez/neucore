<?php

namespace Tests\Unit;

use PHPUnit\Framework\MockObject\Exception;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;
use Tests\RequestFactory;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @throws Exception
     */
    protected function createRequestWithRoute(string $method = 'GET', ?string $path = null): ServerRequestInterface
    {
        $routeParser = $this->createMock(RouteParserInterface::class);
        $routingResults = $this->createMock(RoutingResults::class);
        $route = $this->createMock(RouteInterface::class);

        $request = RequestFactory::createRequest($method, $path ?: '/');
        $request = $request->withAttribute(RouteContext::ROUTE_PARSER, $routeParser);
        $request = $request->withAttribute(RouteContext::ROUTING_RESULTS, $routingResults);

        if ($path) {
            $route->method('getPattern')->willReturn($path);
            $request = $request->withAttribute(RouteContext::ROUTE, $route);
        }

        return $request;
    }
}
