<?php declare(strict_types=1);

namespace Brave\Core;

use Brave\Core\Command\MakeAdmin;
use Brave\Core\Command\UpdateCharacters;
use Brave\Core\Command\UpdatePlayerGroups;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\AppAuth;
use Brave\Core\Service\AutoGroupAssignment;
use Brave\Core\Service\CharacterService;
use Brave\Core\Service\Config;
use Brave\Core\Service\EsiData;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use Brave\Core\Service\UserAuth;
use Brave\Middleware\Cors;
use Brave\Slim\Handlers\Error;
use Brave\Slim\Handlers\PhpError;
use Brave\Slim\Session\NonBlockingSessionMiddleware;
use DI\Container;
use DI\ContainerBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
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
use Tkhamez\Slim\RoleAuth\RoleMiddleware;
use Tkhamez\Slim\RoleAuth\SecureRouteMiddleware;

/**
 * App bootstrapping
 *
 * @SWG\Swagger(
 *     schemes={"https", "http"},
 *     basePath="/api",
 *     @SWG\Info(
 *       title="Brave Collective Core Services API",
 *       description="Client library of Brave Collective Core Services API",
 *       version="0.5.0"
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
 */
class Application
{
    /**
     * @var string
     */
    const ENV_PROD = 'prod';

    /**
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

    /**
     * @var \DI\Container
     */
    private $container;

    public function __construct()
    {
        // set timezone - also used by Doctrine for dates/times in the database
        date_default_timezone_set('UTC');

        // show error until the error handling is setup
        ini_set('display_errors', '1');

        // allow group to change files
        umask(0002);
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
    public function loadSettings(bool $unitTest = false): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        // Load environment variables from file if BRAVECORE_APP_ENV env var is missing
        if (! isset($_SERVER['BRAVECORE_APP_ENV'])) {
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
            $test = include self::ROOT_DIR . '/config/settings_tests.php';
            $this->settings = array_replace_recursive($this->settings, $test);
        }

        return $this->settings;
    }

    /**
     * Returns DI container, builds it if needed.
     *
     * @throws \Exception
     */
    public function getContainer(): Container
    {
        $this->loadSettings();
        if ($this->container === null) {
            $this->buildContainer();
            $this->addDependencies();
        }

        return $this->container;
    }

    /**
     * Creates the Slim app
     *
     * @throws \Exception
     */
    public function getApp(): App
    {
        $this->getContainer();
        $this->errorHandling();
        $this->sessionHandler();

        $app = new App($this->container);

        $this->addMiddleware($app);
        $this->registerRoutes($app);

        return $app;
    }

    /**
     * Creates the Symfony console app.
     *
     * @throws \Exception
     */
    public function getConsoleApp(): ConsoleApplication
    {
        set_time_limit(0);

        $this->getContainer();
        $this->errorHandling();

        $console = new ConsoleApplication();

        $this->addCommands($console);

        return $console;
    }

    /**
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    private function addMiddleware(App $app): void
    {
        // Add middleware, last added are executed first.

        $security = include self::ROOT_DIR . '/config/security.php';
        $app->add(new SecureRouteMiddleware($security));

        $app->add(new RoleMiddleware($this->container->get(AppAuth::class), ['route_pattern' => ['/api/app']]));
        $app->add(new RoleMiddleware($this->container->get(UserAuth::class), ['route_pattern' => ['/api/user']]));

        $app->add(new NonBlockingSessionMiddleware([
            'name' => 'BCSESS',
            'secure' => $this->env === self::ENV_PROD,
            'route_include_pattern' => ['/api/user'],
            'route_blocking_pattern' => [
                '/api/user/auth/login-url',
                '/api/user/auth/callback',
                '/api/user/auth/logout'
            ],
        ]));

        $app->add(new Cors($this->container->get('config')['CORS']['allow_origin']));
    }

    /**
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function buildContainer(): void
    {
        // include config.php from php-di/slim-bridge
        $reflector = new \ReflectionClass(\DI\Bridge\Slim\App::class);
        $bridgeConfig = include dirname($reflector->getFileName()) . '/config.php';

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions($bridgeConfig);
        $containerBuilder->addDefinitions($this->settings);

        $this->container = $containerBuilder->build();
    }

    /**
     * Add dependencies to DI container.
     */
    private function addDependencies(): void
    {
        // Configuration class
        $this->container->set(Config::class, function (ContainerInterface $c) {
            return new Config($c->get('config'));
        });

        // Doctrine
        $this->container->set(EntityManagerInterface::class, function (ContainerInterface $c) {
            $conf = $c->get('config')['doctrine'];
            $config = Setup::createAnnotationMetadataConfiguration(
                $conf['meta']['entity_paths'],
                $conf['meta']['dev_mode'],
                $conf['meta']['proxy_dir']
            );
            return EntityManager::create($conf['connection'], $config);
        });

        // EVE OAuth
        $this->container->set(GenericProvider::class, function (ContainerInterface $c) {
            return new GenericProvider([
                'clientId'                => $c->get('config')['eve']['client_id'],
                'clientSecret'            => $c->get('config')['eve']['secret_key'],
                'redirectUri'             => $c->get('config')['eve']['callback_url'],
                'urlAuthorize'            => 'https://login.eveonline.com/oauth/authorize',
                'urlAccessToken'          => 'https://login.eveonline.com/oauth/token',
                'urlResourceOwnerDetails' => 'https://login.eveonline.com/oauth/verify'
            ]);
        });

        // Monolog
        $this->container->set(LoggerInterface::class, function (ContainerInterface $c) {
            $conf = $c->get('config')['monolog'];
            if (strpos($conf['path'], 'php://') === false) {
                $dir = realpath(dirname($conf['path']));
                if (! is_writable($dir)) {
                    if ($this->env === self::ENV_PROD) {
                        // output message because we may never see it otherwise
                        echo 'Error: the log directory must be writable by the web server.';
                    }
                    throw new \Exception('The log directory ' . $dir . ' must be writable by the web server.');
                }
            }
            $logger = new Logger($conf['name']);
            $logger->pushHandler(new StreamHandler($conf['path'], $conf['level']));
            return $logger;
        });

        $this->container->set(ClientInterface::class, function () {
            return new Client();
        });
    }

    /**
     * Register pdo session handler.
     *
     * (not for CLI)
     *
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @see https://symfony.com/doc/current/components/http_foundation/session_configuration.html
     */
    private function sessionHandler(): void
    {
        if (PHP_SAPI === 'cli') {
            // PHP 7.2 for unit tests:
            // "ini_set(): Headers already sent. You cannot change the session module's ini settings at this time"
            // session_set_save_handler(): Cannot change save handler when headers already sent

            return;
        }

        ini_set('session.gc_maxlifetime', (string) $this->container->get('config')['session']['gc_maxlifetime']);

        $pdo = $this->container->get(EntityManagerInterface::class)->getConnection()->getWrappedConnection();
        $sessionHandler = new PdoSessionHandler($pdo, ['lock_mode' => PdoSessionHandler::LOCK_ADVISORY]);

        session_set_save_handler($sessionHandler, true);
    }

    /**
     * Setup error handling.
     *
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    private function errorHandling(): void
    {
        // Extend Slim's error and php error handler.
        $this->container->set('errorHandler', function (Container $c) {
            return new Error($c->get('settings')['displayErrorDetails'], $c->get(LoggerInterface::class));
        });
        $this->container->set('phpErrorHandler', function (Container $c) {
            return new PhpError($c->get('settings')['displayErrorDetails'], $c->get(LoggerInterface::class));
        });

        // logs errors that are not converted to exceptions by Slim
        ErrorHandler::register($this->container->get(LoggerInterface::class));

        // php settings
        ini_set('display_errors', '0');
        ini_set('log_errors', '0'); // all errors are logged with Monolog
        error_reporting(E_ALL);
    }

    private function registerRoutes(App $app): void
    {
        $routes = include self::ROOT_DIR . '/config/routes.php';

        foreach ($routes as $route => $conf) {
            if ($conf[0] === 'GET') {
                $app->get($route, $conf[1]);
            } elseif ($conf[0] === 'POST') {
                $app->post($route, $conf[1]);
            } elseif ($conf[0] === 'DELETE') {
                $app->delete($route, $conf[1]);
            } elseif ($conf[0] === 'PUT') {
                $app->put($route, $conf[1]);
            }
        }
    }

    /**
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    private function addCommands(ConsoleApplication $console): void
    {
        $console->add(new MakeAdmin(
            $this->container->get(RepositoryFactory::class),
            $this->container->get(ObjectManager::class)
        ));

        $console->add(new UpdateCharacters(
            $this->container->get(RepositoryFactory::class),
            $this->container->get(EsiData::class),
            $this->container->get(CharacterService::class),
            $this->container->get(OAuthToken::class),
            $this->container->get(ObjectManager::class)
        ));

        $console->add(new UpdatePlayerGroups(
            $this->container->get(RepositoryFactory::class),
            $this->container->get(AutoGroupAssignment::class),
            $this->container->get(ObjectManager::class)
        ));
    }
}
