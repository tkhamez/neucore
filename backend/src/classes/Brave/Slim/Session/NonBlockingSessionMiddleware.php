<?php declare(strict_types=1);

namespace Brave\Slim\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Interfaces\RouteInterface;

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
     * route_blocking_pattern <array>: patterns of routes that allow writing to the session, exact match
     * route_include_pattern <array>: if provided only start sessions for this routes, matched by "starts-with"
     *
     * Example
     * [
     *      'cc' => 'MY_SESS',
     *      'secure' => true,
     *      'route_include_pattern' => ['/path/one'],
     *      'route_blocking_pattern' => ['/path/one/set', '/path/one/delete'],
     * ]
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        callable $next): ResponseInterface
    {
        // check if session should be started
        if (! $this->shouldStartSession($request->getAttribute('route'))) {
            return $next($request, $response);
        }

        $this->start();

        $readOnly = $this->isReadOnly($request->getAttribute('route'));

        (new SessionData())->setReadOnly($readOnly);

        if ($readOnly) {
            $this->close();
        }

        return $next($request, $response);
    }

    private function shouldStartSession(RouteInterface $route = null): bool
    {
        $start = false;

        if (isset($this->options['route_include_pattern']) &&
            is_array($this->options['route_include_pattern'])
        ) {
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

    /**
     * @return void
     */
    private function start()
    {
        if (PHP_SAPI !== 'cli') {

            // since PHP 7.2 this emits warnings during unit tests, so no unit tests for this.

            if (isset($this->options['name'])) {
                session_name($this->options['name']);
            }

            session_start([
                'cookie_lifetime' => 0,
                'cookie_path' => '/',
                'cookie_domain' => '',
                'cookie_secure' => isset($this->options['secure']) ? (bool) $this->options['secure'] : true,
                'cookie_httponly' => true,
            ]);

            // write something to the session so that the Set-Cookie header is send
            $_SESSION['_started'] = isset($_SESSION['_started']) ?: time();
        } else {
            // allow unit tests to inject values in the session
            $_SESSION = isset($_SESSION) ? $_SESSION : array();
        }
    }

    private function isReadOnly(RouteInterface $route = null): bool
    {
        $routePattern = $route !== null ? $route->getPattern() : null;
        if ($routePattern === null) {
            return true;
        }

        $readOnly = true;
        if (isset($this->options['route_blocking_pattern']) &&
            is_array($this->options['route_blocking_pattern'])
        ) {
            foreach ($this->options['route_blocking_pattern'] as $blockingPattern) {
                if ($blockingPattern === $routePattern) {
                    $readOnly = false;
                    break;
                }
            }
        }

        return $readOnly;
    }

    /**
     * @return void
     */
    private function close()
    {
        session_write_close();
    }
}
