<?php
namespace Brave\Slim\Role;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Route;

/**
 * Adds roles to the request attribute "roles".
 *
 * Roles usually come from an authenticated user. It's an array
 * with string values, e. g. ['role.one', 'role.two'].
 *
 * Roles are loaded from a RoleProviderInterface object. If that
 * does not return any roles, the role "role.anonymous" is added.
 */
class AuthRoleMiddleware
{

    private $roleService;

    private $options;

    /**
     *
     * Available options (all optional):
     * route_pattern: only authenticate for this routes, matched by "starts-with"
     *
     * Example:
     * ['route_pattern' => ['/path/one', '/path/two']
     */
    public function __construct(RoleProviderInterface $roleService, array $options = [])
    {
        $this->roleService = $roleService;
        $this->options = $options;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        if (! $this->shouldAuthenticate($request->getAttribute('route'))) {
            return $next($request, $response);
        }

        $roles = $this->roleService->getRoles($request);
        if (count($roles) === 0) {
            // no authenticated roles, add anonymous role
            $roles[] = 'role.anonymous';
        }

        $request = $request->withAttribute('roles', $roles);

        return $next($request, $response);
    }

    public function shouldAuthenticate(Route $route = null)
    {
        if (isset($this->options['route_pattern']) && is_array($this->options['route_pattern'])) {
            if ($route === null) {
                return false;
            }
            $routePattern = $route->getPattern();
            foreach ($this->options['route_pattern'] as $includePattern) {
                if (strpos($routePattern, $includePattern) === 0) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }
}
