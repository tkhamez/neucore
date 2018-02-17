<?php
namespace Brave\Slim\Role;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Denies access to a route if the required role is missing.
 *
 * It loads the roles from the request attribute named "roles"
 * (an array with string values, e. g. ['role.one', 'role.two']).
 *
 * The role attribute is provided by the AuthRoleMiddleware class.
 * If it is missing, all routes are allowed.
 */
class SecureRouteMiddleware
{

    private $secured;

    /**
     *
     * First match will be used.
     *
     * Keys can be (matched by "starts-with"):
     * - route pattern
     * - route name
     *
     * Values are roles, only one must match.
     * If no match is found, the route will be allowed.
     *
     * Example:
     * [
     *      '/path/one/public' => ['role.anonymous', 'role.user'],
     *      '/api/one' => ['role.user'],
     * ]
     */
    public function __construct(array $secured)
    {
        $this->secured = $secured;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        /* @var $roles array */
        $roles = $request->getAttribute('roles');
        if ($roles === null) {
            return $next($request, $response);
        }

        /* @var $route \Slim\Route */
        $route = $request->getAttribute('route');
        if ($route === null) {
            return $next($request, $response);
        }

        $allowed = null;

        foreach ($this->secured as $securedRoute => $requiredRoles) {
            foreach ([$route->getName(), $route->getPattern()] as $currentNameOrPattern) {
                if (strpos($currentNameOrPattern, $securedRoute) === 0) {
                    $allowed = false;
                    if (count(array_intersect($requiredRoles, $roles)) > 0) {
                        $allowed = true;
                    }
                    break;
                }
            }
            if ($allowed !== null) {
                break;
            }
        }

        if ($allowed === false) {
            return $response->withStatus(401);
        }

        return $next($request, $response);
    }
}
