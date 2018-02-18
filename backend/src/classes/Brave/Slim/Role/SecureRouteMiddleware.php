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
 * If it is missing, all routes are allowed!
 */
class SecureRouteMiddleware
{

    private $secured;

    /**
     *
     * First match will be used.
     *
     * Keys are route pattern, matched by "starts-with".
     * Values are roles, only one must match.
     *
     * Example:
     * [
     *      '/api/one/public' => ['anonymous', 'user'],
     *      '/api/one' => ['user'],
     * ]
     */
    public function __construct(array $secured)
    {
        $this->secured = $secured;
    }

    /**
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        /* @var $roles array */
        $roles = $request->getAttribute('roles');

        /* @var $route \Slim\Route */
        $route = $request->getAttribute('route');
        if ($route === null) {
            return $next($request, $response);
        }

        $allowed = null;

        foreach ($this->secured as $securedRoute => $requiredRoles) {
            if (strpos($route->getPattern(), $securedRoute) === 0) {
                $allowed = false;
                if (is_array($roles) && count(array_intersect($requiredRoles, $roles)) > 0) {
                    $allowed = true;
                }
                break;
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
