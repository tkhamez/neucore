<?php
namespace Brave\Core;

use Brave\Core\Command\MakeAdmin;
use Brave\Core\Command\Sample;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\RoleRepository;
use Brave\Core\Service\AppAuthService;
use Brave\Core\Service\EveService;
use Brave\Core\Service\UserAuthService;
use Brave\Middleware\Cors;
use Brave\Slim\Handlers\Error;
use Brave\Slim\Handlers\PhpError;
use Brave\Slim\Role\AuthRoleMiddleware;
use Brave\Slim\Role\SecureRouteMiddleware;
use Brave\Slim\Session\NonBlockingSessionMiddleware;

use DI\Container;
use DI\ContainerBuilder;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;

use League\OAuth2\Client\Provider\GenericProvider;

use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use Slim\App;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Whoops\Handler\PlainTextHandler;

/**
 * App bootstrapping
 *
 * @SWG\Swagger(
 *     schemes={"https"},
 *     basePath="/api",
 *     @SWG\Info(
 *       title="Brave Collective Core Services API",
 *       version="0.1"
 *     ),
 *     @SWG\SecurityScheme(
 *         securityDefinition="Bearer",
 *         type="apiKey",
 *         name="Authorization",
 *         in="header",
 *         description="Example: Bearer ABC"
 *     ),
 *     @SWG\SecurityScheme(
 *         securityDefinition="Session",
 *         type="apiKey",
 *         name="Cookie",
 *         in="header",
 *         description="Example: BCSESS=123"
 *     )
 * )
 * @SWG\Tag(
 *     name="User",
 *     description="API for the frond-end.",
 * )
 * @SWG\Tag(
 *     name="App",
 *     description="API for 3rd party apps.",
 * )
 */
class Application
{

    /**
     *
     * @var string
     */
    const ENV_PROD = 'prod';

    /**
     *
     * @var string
     */
    const ENV_DEV = 'dev';

    /**
     * Path to application root directory.
     *
     * Does not have a trailing slash.
     *
     * @var string
     */
    const ROOT_DIR = __DIR__ . '/../../../..';

    /**
     * Setting from the config dir.
     *
     * @var array
     */
    private $settings;

    /**
     * The current environment.
     *
     * self::ENV_PROD or self::ENV_DEV
     *
     * @var string
     */
    private $env;

    public function __construct()
    {
        // set timezone - also used by Doctrine for dates/times in the database
        date_default_timezone_set('UTC');
    }

    /**
     * Loads settings based on environment.
     *
     * Development or production environment is determined by
     * the environment variable BRAVECORE_APP_ENV.
     *
     * Loads environment variables from a .env file if the environment variable
     * BRAVECORE_APP_ENV does not exist and the composer package symfony/dotenv is available.
     * Both should only be true for the development environment.
     *
     * @param bool $unitTest Indicates if the app is running from a functional (integration) test.
     * @return array
     */
    public function loadSettings($unitTest = false): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        // Load env vars from file, the check is to ensure we don't use .env in production
        if (! isset($_SERVER['BRAVECORE_APP_ENV']) && class_exists(Dotenv::class)) {
            (new Dotenv())->load(Application::ROOT_DIR . '/.env');
        }

        if (! isset($_SERVER['BRAVECORE_APP_ENV'])) {
            throw new \RuntimeException(
                'BRAVECORE_APP_ENV environment variable is not defined. '.
                'You need to define environment variables for configuration '.
                'or load variables from a .env file (see .env.dist file).'
            );
        }

        if ($_SERVER['BRAVECORE_APP_ENV'] === self::ENV_PROD) {
            $this->env = self::ENV_PROD;
        } else {
            $this->env = self::ENV_DEV;
        }

        $this->settings = require self::ROOT_DIR . '/config/settings.php';

        if (PHP_SAPI === 'cli') {
            $cli = require self::ROOT_DIR . '/config/settings_cli.php';
            $this->settings = array_replace_recursive($this->settings, $cli);

        } elseif (strpos(getenv('PATH'), '/app/.heroku/php/') !== false) {
            $heroku = require self::ROOT_DIR . '/config/settings_heroku.php';
            $this->settings = array_replace_recursive($this->settings, $heroku);
        }

        if ($this->env === self::ENV_DEV) {
            $dev = require self::ROOT_DIR . '/config/settings_dev.php';
            $this->settings = array_replace_recursive($this->settings, $dev);
        }

        if ($unitTest) {
            $test = include Application::ROOT_DIR . '/config/settings_tests.php';
            $this->settings = array_replace_recursive($this->settings, $test);
        }

        return $this->settings;
    }

    /**
     * Creates the Slim app
     *
     * @param bool $withMiddleware Optional, defaults to true
     * @param bool $withRoutes Optional, defaults to true
     * @return App
     */
    public function getApp($withMiddleware = true, $withRoutes = true): App
    {
        $this->loadSettings();

        $container = $this->buildContainer();
        $app = new App($container);

        $this->dependencies($container);
        $this->sessionHandler($container);
        $this->errorHandling($container);

        if ($withMiddleware) {
            $this->addMiddleware($app);
        }

        if ($withRoutes) {
            $this->routes($app);
        }

        return $app;
    }

    /**
     * Creates the Symfony console app.
     *
     * @return ConsoleApplication
     */
    public function getConsoleApp()
    {
        set_time_limit(0);

        $app = $this->getApp(true, false); // with middleware, without routes
        $c = $app->getContainer();

        $console = new ConsoleApplication();

        $console->add(new MakeAdmin(
            $c->get(CharacterRepository::class),
            $c->get(RoleRepository::class),
            $c->get(EntityManagerInterface::class),
            $c->get(LoggerInterface::class)
        ));

        $console->add(new Sample(
            $c->get(CharacterRepository::class),
            $c->get(EveService::class)
        ));

        return $console;
    }

    /**
     *
     * @param App $app
     * @return void
     */
    public function addMiddleware(App $app)
    {
        $c = $app->getContainer();

        // Add middleware, last added are executed first.

        $security = include self::ROOT_DIR . '/config/security.php';
        $app->add(new SecureRouteMiddleware($security));

        $app->add(new AuthRoleMiddleware($c->get(AppAuthService::class), ['route_pattern' => ['/api/app']]));
        $app->add(new AuthRoleMiddleware($c->get(UserAuthService::class), ['route_pattern' => ['/api/user']]));

        $app->add(new NonBlockingSessionMiddleware([
            'name' => 'BCSESS',
            'secure' => $this->env === self::ENV_PROD,
            'route_include_pattern' => ['/api/user'],
            'route_blocking_pattern' => [
                '/api/user/auth/login',
                '/api/user/auth/login-alt',
                '/api/user/auth/callback',
                '/api/user/auth/logout'
            ],
        ]));

        $app->add(new Cors($c->get('config')['CORS']['allow_origin']));
    }

    private function buildContainer(): Container
    {
        // include config.php from php-di/slim-bridge
        $reflector = new \ReflectionClass('DI\Bridge\Slim\App');
        $bridgeConfig = include dirname($reflector->getFileName()) . '/config.php';

        // Disable Slim’s error handling for dev env.
        // see also https://www.slimframework.com/docs/v3/handlers/error.html
        if ($this->env === Application::ENV_DEV) {
            // Values cannot be unset from the DI\Container,
            // so it must be done in the configuration before it is built.
            unset($bridgeConfig['errorHandler']);
            unset($bridgeConfig['phpErrorHandler']);
        }

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions($bridgeConfig);
        $containerBuilder->addDefinitions($this->settings);

        return $containerBuilder->build();
    }

    /**
     * Add dependencies to DI container.
     *
     * @return void
     */
    private function dependencies(Container $container)
    {
        // Doctrine
        $container->set(EntityManagerInterface::class, function(ContainerInterface $c) {
            $conf = $c->get('config')['doctrine'];
            $config = Setup::createAnnotationMetadataConfiguration(
                $conf['meta']['entity_path'],
                $conf['meta']['dev_mode'],
                $conf['meta']['proxy_dir']
            );
            return EntityManager::create($conf['connection'], $config);
        });

        // EVE OAuth
        $container->set(GenericProvider::class, new GenericProvider([
            'clientId'                => $container->get('config')['eve']['client_id'],
            'clientSecret'            => $container->get('config')['eve']['secret_key'],
            'redirectUri'             => $container->get('config')['eve']['callback_url'],
            'urlAuthorize'            => 'https://login.eveonline.com/oauth/authorize',
            'urlAccessToken'          => 'https://login.eveonline.com/oauth/token',
            'urlResourceOwnerDetails' => 'https://login.eveonline.com/oauth/verify'
        ]));

        // Monolog
        $container->set(LoggerInterface::class, function(ContainerInterface $c) {
            $conf = $c->get('config')['monolog'];
            if (strpos($conf['path'], 'php://') === false) {
                $dir = realpath(dirname($conf['path']));
                if (! is_writable($dir)) {
                    throw new \Exception('The log directory ' . $dir . ' must be writable by the webserver.');
                }
            }
            $logger = new Logger($conf['name']);
            $logger->pushHandler(new StreamHandler($conf['path'], $conf['level']));
            return $logger;
        });
    }

    /**
     * Register pdo session handler.
     *
     * (not for CLI)
     *
     * @param Container $container
     * @return void
     * @see https://symfony.com/doc/current/components/http_foundation/session_configuration.html
     */
    private function sessionHandler(Container $container)
    {
        if (PHP_SAPI === 'cli') {
            // PHP 7.2 for unit tests:
            // "ini_set(): Headers already sent. You cannot change the session module's ini settings at this time"
            // session_set_save_handler(): Cannot change save handler when headers already sent

            return;
        }

        ini_set('session.gc_maxlifetime', $container->get('config')['session']['gc_maxlifetime']);

        $pdo = $container->get(EntityManagerInterface::class)->getConnection()->getWrappedConnection();
        $sessionHandler = new PdoSessionHandler($pdo, ['lock_mode' => PdoSessionHandler::LOCK_ADVISORY]);

        session_set_save_handler($sessionHandler, true);
    }

    /**
     * Setup error handling.
     *
     * @param Container $container
     * @return void
     */
    private function errorHandling(Container $container)
    {
        // php settings
        ini_set('display_errors', 0); // all errors are shown with whoops in dev mode
        ini_set('log_errors', 0); // all errors are logged with Monolog in prod mode
        error_reporting(E_ALL);

        if ($this->env === self::ENV_PROD) {
            // Extend Slim's error and php error handler.
            $container->set('errorHandler', function ($c) {
                return new Error($c->get('settings')['displayErrorDetails'], $c->get(LoggerInterface::class));
            });
            $container->set('phpErrorHandler', function ($c) {
                return new PhpError($c->get('settings')['displayErrorDetails'], $c->get(LoggerInterface::class));
            });

            // logs errors that are not converted to exceptions by Slim
            ErrorHandler::register($container->get(LoggerInterface::class));

        } else { // self::ENV_DEV
            // Slim's error handling is not added to the container in
            // self::buildContainer() for dev env, instead we use Whoops
            $whoops = new Run();
            if (PHP_SAPI === 'cli') {
                $whoops->pushHandler(new PlainTextHandler());
            } elseif (isset($_SERVER['HTTP_ACCEPT']) && $_SERVER['HTTP_ACCEPT'] === 'application/json') {
                $whoops->pushHandler((new JsonResponseHandler())->addTraceToOutput(true));
            } else {
                $whoops->pushHandler(new PrettyPageHandler());
            }
            $whoops->register();
        }
    }

    /**
     *
     * @param App $app
     * @return void
     */
    private function routes(App $app)
    {
        $routes = include self::ROOT_DIR . '/config/routes.php';

        foreach ($routes as $route => $conf) {
            if (is_array($conf[0])) { // e. g. ['GET', 'POST']
                $app->map($conf[0], $route, $conf[1]);

            } elseif ($conf[0] === 'GET') {
                $app->get($route, $conf[1]);

            } elseif ($conf[0] === 'POST') {
                $app->post($route, $conf[1]);

            } elseif ($conf[0] === 'DELETE') {
                $app->delete($route, $conf[1]);

            } elseif ($conf[0] === 'PUT') {
                $app->put($route, $conf[1]);

            } else {
                // add as needed:
                // options, patch, any
            }
        }
    }
}
