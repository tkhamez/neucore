<?php

declare(strict_types=1);

namespace Neucore\Slim;

use Neucore\Service\SessionData;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Routing\RouteContext;

/**
 * Starts the session, reads the session data and immediately closes the session again
 * (ready only session).
 *
 * After this middleware was executed, the class SessionData can be used to access
 * the session data (any object of that class, even if it was created before the session was started).
 *
 * Can optionally be writable (blocking) for certain routes, so session will not be closed for these.
 * Can optionally be restricted to certain routes, so session will not be started for any other route.
 */
class SessionMiddleware implements MiddlewareInterface
{
    const OPTION_ROUTE_INCLUDE_PATTERN  = 'route_include_pattern';

    const OPTION_ROUTE_BLOCKING_PATTERN  = 'route_blocking_pattern';

    const OPTION_SECURE  = 'secure';

    const OPTION_SAME_SITE  = 'same_site';

    const OPTION_NAME  = 'name';

    /**
     * @var array
     */
    private $options;

    /**
     *
     * Available options (all optional):
     * name <string>: the session name
     * secure <bool>: session.cookie_secure option runtime configuration
     * same_site <bool>: session.same_site option runtime configuration
     * route_blocking_pattern <array>: patterns of routes that allow writing to the session, matched by "starts-with"
     * route_include_pattern <array>: if provided only start sessions for this routes, matched by "starts-with"
     *
     *  The route_* options need the Slim routing middleware ($app->addRoutingMiddleware()).
     *
     * Example
     * [
     *      'name' => 'MY_SESS',
     *      'secure' => true,
     *      'same_site' => 'Lax',
     *      'route_include_pattern' => ['/path/one'],
     *      'route_blocking_pattern' => ['/path/one/set', '/path/one/delete'],
     * ]
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = RouteContext::fromRequest($request)->getRoute();

        if (! $this->shouldStartSession($route)) {
            return $handler->handle($request);
        }

        $this->start();
        $readOnly = $this->isReadOnly($route);
        SessionData::setReadOnly($readOnly);
        if ($readOnly) {
            session_write_close();
        }

        return $handler->handle($request);
    }

    private function shouldStartSession(RouteInterface $route = null): bool
    {
        $start = false;

        if (isset($this->options[self::OPTION_ROUTE_INCLUDE_PATTERN]) &&
            is_array($this->options[self::OPTION_ROUTE_INCLUDE_PATTERN])
        ) {
            if ($route === null) {
                return false;
            }
            $routePattern = $route->getPattern();
            foreach ($this->options[self::OPTION_ROUTE_INCLUDE_PATTERN] as $includePattern) {
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
            if (isset($this->options[self::OPTION_NAME])) {
                session_name($this->options[self::OPTION_NAME]);
            }

            session_start([
                'cookie_lifetime' => 0,
                'cookie_path' => '/',
                'cookie_domain' => '',
                'cookie_secure' =>
                    isset($this->options[self::OPTION_SECURE]) ?
                    (bool) $this->options[self::OPTION_SECURE] :
                    true,
                'cookie_httponly' => true,
                'cookie_samesite' =>
                    isset($this->options[self::OPTION_SAME_SITE]) ?
                    $this->options[self::OPTION_SAME_SITE] :
                    'Lax',
            ]);

            // write something to the session so that the Set-Cookie header is send
            $_SESSION['_started'] = $_SESSION['_started'] ?? time();
        } else {
            // allow unit tests to inject values in the session
            $_SESSION = $_SESSION ?? array();
        }
    }

    private function isReadOnly(RouteInterface $route = null): bool
    {
        $routePattern = $route !== null ? $route->getPattern() : null;
        if ($routePattern === null) {
            return true;
        }

        $readOnly = true;
        if (isset($this->options[self::OPTION_ROUTE_BLOCKING_PATTERN]) &&
            is_array($this->options[self::OPTION_ROUTE_BLOCKING_PATTERN])
        ) {
            foreach ($this->options[self::OPTION_ROUTE_BLOCKING_PATTERN] as $blockingPattern) {
                if (strpos($routePattern, $blockingPattern) === 0) {
                    $readOnly = false;
                    break;
                }
            }
        }

        return $readOnly;
    }
}
