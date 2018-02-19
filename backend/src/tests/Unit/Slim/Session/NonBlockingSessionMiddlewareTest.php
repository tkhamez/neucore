<?php
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
        session_name('PHPSESSID');
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

    public function testWithDefaultOptions()
    {
        $this->invokeMiddleware('/sess/set', [], true);

        $this->assertTrue(isset($_SESSION));
        $this->assertTrue((new SessionData())->isReadOnly());
        $this->assertTrue(session_get_cookie_params()['httponly']);
        $this->assertSame('PHPSESSID', session_name());
        $this->assertTrue(session_get_cookie_params()['secure']);
        $this->assertSame(1440, session_get_cookie_params()['lifetime']);
    }

    public function testWithCustomOptions()
    {
        $conf = [
            'name' => 'TEST_SESS',
            'secure' => false,
            'lifetime' => 3600,
        ];
        $this->invokeMiddleware('/sess/set', $conf, true);

        $this->assertSame('TEST_SESS', session_name());
        $this->assertFalse(session_get_cookie_params()['secure']);
        $this->assertSame(3600, session_get_cookie_params()['lifetime']);
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

        $next = function($req, $res) {
            return $res;
        };

        return $nbs($req, new Response(), $next);
    }
}
