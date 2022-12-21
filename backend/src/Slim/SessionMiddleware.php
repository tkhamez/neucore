<?php

declare(strict_types=1);

namespace Neucore\Slim;

use Neucore\Controller\User\AuthController;
use Neucore\Factory\SessionHandlerFactory;
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
    public const OPTION_ROUTE_INCLUDE_PATTERN  = 'route_include_pattern';

    public const OPTION_ROUTE_BLOCKING_PATTERN  = 'route_blocking_pattern';

    public const OPTION_SECURE  = 'secure';

    public const OPTION_NAME  = 'name';

    private SessionHandlerFactory $sessionHandlerFactory;

    private array $options;

    /**
     *
     * Available options (all optional):
     * name <string>: the session name
     * secure <bool>: session.cookie_secure option runtime configuration
     * route_blocking_pattern <array>: patterns of routes that allow writing to the session, matched by "starts-with"
     * route_include_pattern <array>: if provided only start sessions for this routes, matched by "starts-with"
     *
     *  The route_* options need the Slim routing middleware ($app->addRoutingMiddleware()).
     *
     * Example
     * [
     *      'name' => 'MY_SESS',
     *      'secure' => true,
     *      'route_include_pattern' => ['/path/one'],
     *      'route_blocking_pattern' => ['/path/one/set', '/path/one/delete'],
     * ]
     */
    public function __construct(SessionHandlerFactory $sessionHandlerFactory, array $options = [])
    {
        $this->sessionHandlerFactory = $sessionHandlerFactory;
        $this->options = $options;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = RouteContext::fromRequest($request)->getRoute();

        if (!$this->shouldStartSession($route)) {
            return $handler->handle($request);
        }

        $this->setup();
        $this->start();

        $readOnly = $this->isReadOnly($route);
        SessionData::setReadOnly($readOnly);
        if ($readOnly) {
            session_write_close();
        }

        $response = $handler->handle($request);

        if ($response->hasHeader(AuthController::HEADER_LOGIN) && PHP_SAPI !== 'cli') {
            session_regenerate_id();
        }

        return $response;
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
                if (str_starts_with($routePattern, $includePattern)) {
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
     * Register session handler.
     */
    private function setup(): void
    {
        if (headers_sent()) { // should only be true for integration tests
            return;
        }

        ini_set('session.gc_maxlifetime', '1440'); // 24 minutes
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_save_handler(($this->sessionHandlerFactory)(), true);
    }

    private function start(): void
    {
        if (PHP_SAPI !== 'cli') {
            if (isset($this->options[self::OPTION_NAME])) {
                session_name($this->options[self::OPTION_NAME]);
            }

            session_start([
                'cookie_lifetime' => 0,
                'cookie_path' => '/',
                'cookie_domain' => '',
                'cookie_secure' => !isset($this->options[self::OPTION_SECURE]) || $this->options[self::OPTION_SECURE],
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax', // Needs to be Lax for OAuth to work.
            ]);

            // write something to the session so that the Set-Cookie header is sent
            $_SESSION['_started'] = $_SESSION['_started'] ?? time();
        } else {
            // allow unit tests to inject values in the session
            $_SESSION = $_SESSION ?? array();
        }
    }

    private function isReadOnly(RouteInterface $route = null): bool
    {
        $routePattern = $route?->getPattern();
        if ($routePattern === null) {
            return true;
        }

        $readOnly = true;
        if (isset($this->options[self::OPTION_ROUTE_BLOCKING_PATTERN]) &&
            is_array($this->options[self::OPTION_ROUTE_BLOCKING_PATTERN])
        ) {
            foreach ($this->options[self::OPTION_ROUTE_BLOCKING_PATTERN] as $blockingPattern) {
                if (str_starts_with($routePattern, $blockingPattern)) {
                    $readOnly = false;
                    break;
                }
            }
        }

        return $readOnly;
    }
}
