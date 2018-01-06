<?php
namespace Brave\Slim\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Route;

/**
 * A non-blocking (read-only) Session.
 *
 * This starts the session, reads the session data and immediately closes the session again.
 *
 * After this middleware was executed, the class SessionData can be used to access
 * the session data (any object of that class, even if it was created before this).
 *
 * Can optionally be writable (blocking) for certain routes, so session will not be closed for these.
 * Can optionally be restricted to certain routes, so session will not be started for any other route.
 */
class NonBlockingSessionMiddleware
{

    private $options;

    /**
     *
     * Available options (all optional):
     * name <string>: the session name
     * secure <bool>: session.cookie_secure option runtime configuration
     * route_blocking_pattern <array>: patterns of routes that allow writing to the session
     * route_include_pattern <array>: only start sessions for this routes, matched by "starts-with"
     *
     * Example
     * [
     *      'name' => 'MY_SESS',
     *      'secure' => true,
     *      'route_include_pattern' => ['/path/one'],
     *      'route_blocking_pattern' => ['/path/one/set', '/path/one/delete'],
     * ]
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        // check if session should be started
        if (! $this->startSession($request->getAttribute('route'))) {
            return $next($request, $response);
        }

        if (PHP_SAPI === 'cli') {
            $_SESSION = array();
        } else {
            $this->start();
        }

        $readOnly = $this->isReadOnly($request->getAttribute('route'));

        (new SessionData())->setReadOnly($readOnly);

        if ($readOnly && PHP_SAPI !== 'cli') {
            $this->close();
        }

        return $next($request, $response);
    }

    private function startSession(Route $route = null)
    {
        $start = false;

        if (isset($this->options['route_include_pattern']) && is_array($this->options['route_include_pattern'])) {
            if ($route === null) {
                return false;
            }
            $routePattern = $route->getPattern();
            foreach ($this->options['route_include_pattern'] as $includePattern) {
                if (strpos($routePattern, $includePattern) === 0) {
                    $start = true;
                    break;
                }
            }
        } else {
            $start = true;
        }

        return $start;
    }

    private function start()
    {
        if (isset($this->options['name'])) {
            session_name($this->options['name']);
        }

        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => $this->options['secure']
        ]);
    }

    private function isReadOnly(Route $route = null)
    {
        $routePattern = $route !== null ? $route->getPattern() : null;
        if ($routePattern === null) {
            return true;
        }

        $readOnly = true;
        if (isset($this->options['route_blocking_pattern']) && is_array($this->options['route_blocking_pattern'])) {
            foreach ($this->options['route_blocking_pattern'] as $blockingPattern) {
                if ($blockingPattern === $routePattern) {
                    $readOnly = false;
                    break;
                }
            }
        }

        return $readOnly;
    }

    private function close()
    {
        session_write_close();
    }
}
