<?php
namespace Tests\Unit\Slim\Role;

use Brave\Core\Roles;
use Brave\Slim\Role\AuthRoleMiddleware;
use Brave\Slim\Role\RoleProviderInterface;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Route;

class AuthRoleMiddlewareTest extends \PHPUnit\Framework\TestCase
{

    public function testAddsRolesForPaths()
    {
        $test = $this;
        $next = function($req) use ($test)  {
            $test->assertSame(['r1', 'r2'], $req->getAttribute('roles'));
        };

        $this->invokeMiddleware('/path1',    ['/path1', '/path2'], ['r1', 'r2'], $next, true);
        $this->invokeMiddleware('/path23/4', ['/path1', '/path2'], ['r1', 'r2'], $next, true);
    }

    public function testAddsRoleAnonymous()
    {
        $test = $this;
        $next = function($req) use ($test)  {
            $test->assertSame([Roles::ANONYMOUS], $req->getAttribute('roles'));
        };

        $this->invokeMiddleware('/path1', ['/path1'], [], $next, true);
    }

    public function testDoesNotAddRolesForOtherPaths()
    {
        $test = $this;
        $next = function($req) use ($test)  {
            $test->assertNull($req->getAttribute('roles'));
        };

        $this->invokeMiddleware('/other/path', ['/path1'], ['role1'], $next, true);
        $this->invokeMiddleware('/not/path1',  ['/path1'], ['role1'], $next, true);
    }

    public function testDoesNotAddRolesWithoutPattern()
    {
        $test = $this;
        $next = function($req) use ($test)  {
            $test->assertNull($req->getAttribute('roles'));
        };

        $this->invokeMiddleware('/path1', null, ['role1'], $next, true);
        $this->invokeMiddleware('/path1', [],   ['role1'], $next, true);
    }

    public function testDoesNotAddRolesWithoutRouteAttribute()
    {
        $test = $this;
        $next = function($req) use ($test)  {
            $test->assertNull($req->getAttribute('roles'));
        };

        $this->invokeMiddleware('/path1', ['/path1'], ['role1'], $next, false);
    }

    private function invokeMiddleware($path, $routes, $roles, $next, $addRole)
    {
        $route = $this->createMock(Route::class);
        $route->method('getPattern')->willReturn($path);

        $req = Request::createFromEnvironment(Environment::mock());
        if ($addRole) {
            $req = $req->withAttribute('route', $route);
        }

        $role = $this->createMock(RoleProviderInterface::class);
        $role->method('getRoles')->willReturn($roles);

        $arm = new AuthRoleMiddleware($role, ['route_pattern' =>  $routes]);

        $arm($req, new Response(), $next);
    }
}
