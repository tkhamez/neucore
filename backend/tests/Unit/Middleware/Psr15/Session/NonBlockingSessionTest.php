<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15\Session;

use Neucore\Middleware\Psr15\Session\NonBlockingSession;
use Neucore\Middleware\Psr15\Session\SessionData;
use PHPUnit\Framework\TestCase;
use Slim\Interfaces\RouteInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Routing\RouteContext;
use Slim\Routing\RoutingResults;
use Tests\RequestFactory;
use Tests\RequestHandler;

class NonBlockingSessionTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SESSION);
    }

    public function testShouldNotStart()
    {
        $conf = [
            'route_include_pattern' => ['/sess'],
            'route_blocking_pattern' => ['/sess/set', '/sess/delete'],
        ];
        $this->invokeMiddleware($conf, '/no-sess');

        $this->assertFalse(isset($_SESSION));
    }

    public function testDoesNotStartWithoutRouteAndWithPattern()
    {
        $conf = [
            'route_include_pattern' => ['/sess'],
        ];
        $this->invokeMiddleware($conf); #  '/sess/readonly'

        $this->assertFalse(isset($_SESSION));
    }

    public function testStartsWithoutRouteAndWithoutPattern()
    {
        $this->invokeMiddleware([]); # '/sess/readonly'

        $this->assertTrue(isset($_SESSION));
    }

    public function testStartReadOnly()
    {
        $conf = [
            'route_include_pattern' => ['/sess'],
            'route_blocking_pattern' => ['/sess/set', '/sess/delete'],
        ];
        $this->invokeMiddleware($conf, '/sess');

        $this->assertTrue(isset($_SESSION));
        $this->assertTrue(SessionData::isReadOnly());
    }

    public function testStartWritable()
    {
        $conf = [
            'route_include_pattern' => ['/sess'],
            'route_blocking_pattern' => ['/sess/set', '/sess/delete'],
        ];
        $this->invokeMiddleware($conf, '/sess/set');

        $this->assertTrue(isset($_SESSION));
        $this->assertFalse(SessionData::isReadOnly());
    }

    public function testStartWritableStartsWith()
    {
        $conf = [
            'route_include_pattern' => ['/sess'],
            'route_blocking_pattern' => ['/sess', '/sess/delete'],
        ];
        $this->invokeMiddleware($conf, '/sess/set');

        $this->assertTrue(isset($_SESSION));
        $this->assertFalse(SessionData::isReadOnly());
    }

    private function invokeMiddleware($conf, $path = null)
    {
        $routeParser = $this->createMock(RouteParserInterface::class);
        $routingResults = $this->createMock(RoutingResults::class);
        $route = $this->createMock(RouteInterface::class);

        $request = RequestFactory::createRequest();
        $request = $request->withAttribute(RouteContext::ROUTE_PARSER, $routeParser);
        $request = $request->withAttribute(RouteContext::ROUTING_RESULTS, $routingResults);

        if ($path) {
            $route->method('getPattern')->willReturn($path);
            $request = $request->withAttribute(RouteContext::ROUTE, $route);
        }

        $nbs = new NonBlockingSession($conf);

        return $nbs->process($request, new RequestHandler());
    }
}
