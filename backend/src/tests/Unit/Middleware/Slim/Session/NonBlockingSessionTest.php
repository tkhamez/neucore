<?php declare(strict_types=1);

namespace Tests\Unit\Middleware\Slim\Session;

use Neucore\Factory\ResponseFactory;
use Neucore\Middleware\Slim\Session\NonBlockingSession;
use Neucore\Middleware\Slim\Session\SessionData;
use PHPUnit\Framework\TestCase;
use Slim\Interfaces\RouteInterface;
use Tests\RequestFactory;

class NonBlockingSessionTest extends TestCase
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
        $this->invokeMiddleware('/no-sess', $conf, true);

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

    public function testStartWritableStartsWith()
    {
        $conf = [
            'route_include_pattern' => ['/sess'],
            'route_blocking_pattern' => ['/sess', '/sess/delete'],
        ];
        $this->invokeMiddleware('/sess/set', $conf, true);

        $this->assertTrue(isset($_SESSION));
        $this->assertFalse((new SessionData())->isReadOnly());
    }

    private function invokeMiddleware($path, $conf, $addRoute)
    {
        $route = $this->createMock(RouteInterface::class);
        $route->method('getPattern')->willReturn($path);

        $req = RequestFactory::createRequest();
        if ($addRoute) {
            $req = $req->withAttribute('route', $route);
        }

        $nbs = new NonBlockingSession($conf);

        $next = function (/** @noinspection PhpUnusedParameterInspection */$req, $res) {
            return $res;
        };

        return $nbs($req, (new ResponseFactory())->createResponse(), $next);
    }
}
