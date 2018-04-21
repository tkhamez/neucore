<?php declare(strict_types=1);

namespace Tests\Unit\Slim\Session;

use Brave\Slim\Session\NonBlockingSessionMiddleware;
use Brave\Slim\Session\SessionData;
use Slim\Route;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;

class NonBlockingSessionMiddlewareTest extends \PHPUnit\Framework\TestCase
{
    public function setUp()
    {
        unset($_SESSION);
    }

    public function testShouldNotStart()
    {
        $conf = [
            'route_include_pattern' => ['/sess'],
            'route_blocking_pattern' => ['/sess/set', '/sess/delete'],
        ];
        $this->invokeMiddleware('/nosess', $conf, true);

        $this->assertFalse(isset($_SESSION));
    }

    public function testDoesNotStartWithoutRouteAndWithPattern()
    {
        $conf = [
            'route_include_pattern' => ['/sess'],
        ];
        $this->invokeMiddleware('/sess/readonly', $conf, false);

        $this->assertFalse(isset($_SESSION));
    }

    public function testStartsWithoutRouteAndWithoutPattern()
    {
        $this->invokeMiddleware('/sess/readonly', [], false);

        $this->assertTrue(isset($_SESSION));
    }

    public function testStartReadOnly()
    {
        $conf = [
            'route_include_pattern' => ['/sess'],
            'route_blocking_pattern' => ['/sess/set', '/sess/delete'],
        ];
        $this->invokeMiddleware('/sess', $conf, true);

        $this->assertTrue(isset($_SESSION));
        $this->assertTrue((new SessionData())->isReadOnly());
    }

    public function testStartWritable()
    {
        $conf = [
            'route_include_pattern' => ['/sess'],
            'route_blocking_pattern' => ['/sess/set', '/sess/delete'],
        ];
        $this->invokeMiddleware('/sess/set', $conf, true);

        $this->assertTrue(isset($_SESSION));
        $this->assertFalse((new SessionData())->isReadOnly());
    }

    private function invokeMiddleware($path, $conf, $addRoute)
    {
        $route = $this->createMock(Route::class);
        $route->method('getPattern')->willReturn($path);

        $req = Request::createFromEnvironment(Environment::mock());
        if ($addRoute) {
            $req = $req->withAttribute('route', $route);
        }

        $nbs = new NonBlockingSessionMiddleware($conf);

        $next = function ($req, $res) {
            return $res;
        };

        return $nbs($req, new Response(), $next);
    }
}
