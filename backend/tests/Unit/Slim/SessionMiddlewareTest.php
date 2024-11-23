<?php

declare(strict_types=1);

namespace Tests\Unit\Slim;

use Neucore\Factory\SessionHandlerFactory;
use Neucore\Slim\SessionMiddleware;
use Neucore\Service\SessionData;
use Tests\Helper;
use Tests\RequestHandler;
use Tests\Unit\TestCase;

class SessionMiddlewareTest extends TestCase
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

    private function invokeMiddleware(array $conf, ?string $path = null): void
    {
        $request = $this->createRequestWithRoute('GET', $path);

        $em = (new Helper())->getEm();
        $nbs = new SessionMiddleware(new SessionData, new SessionHandlerFactory($em), $conf);

        $nbs->process($request, new RequestHandler());
    }
}
