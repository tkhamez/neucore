<?php

declare(strict_types=1);

namespace Neucore;

use DI\Bridge\Slim\Bridge;
use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Source\SourceCache;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\ErrorHandler;
use Neucore\Command\CheckTokens;
use Neucore\Command\CleanHttpCache;
use Neucore\Command\ClearCache;
use Neucore\Command\DBVerifySSL;
use Neucore\Command\DoctrineFixturesLoad;
use Neucore\Command\MakeAdmin;
use Neucore\Command\RevokeToken;
use Neucore\Command\SendInvalidTokenMail;
use Neucore\Command\UpdateCharacters;
use Neucore\Command\UpdateMemberTracking;
use Neucore\Command\UpdatePlayerGroups;
use Neucore\Middleware\Psr15\Cors;
use Neucore\Middleware\Psr15\BodyParams;
use Neucore\Middleware\Psr15\Session\NonBlockingSession;
use Neucore\Service\AppAuth;
use Neucore\Service\Config;
use Neucore\Service\UserAuth;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Tkhamez\Slim\RoleAuth\RoleMiddleware;
use Tkhamez\Slim\RoleAuth\SecureRouteMiddleware;

/**
 * App bootstrapping
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
     * @var string
     */
    const RUN_WEB = 'web';

    /**
     * @var string
     */
    const RUN_CONSOLE = 'console';

    /**
     * Path to application root directory.
     *
     * Does not have a trailing slash.
     *
     * @var string
     */
    const ROOT_DIR = __DIR__ . '/..';

    /**
     * App setting from the config dir.
     *
     * @var Config|null
     */
    private $config;

    /**
     * The current environment.
     *
     * self::ENV_PROD or self::ENV_DEV
     *
     * @var string
     */
    private $env;

    /**
     * @var string|null self::RUN_WEB or self::RUN_CONSOLE
     */
    private $runEnv;

    /**
     * @var Container
     */
    private $container;

    public function __construct()
    {
        // set timezone - also used by Doctrine for dates/times in the database
        date_default_timezone_set('UTC');

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
     * BRAVECORE_APP_ENV does not exist.
     *
     * @param bool $unitTest Indicates if the app is running from a functional (integration) test.
     * @param bool $forceDevMode Only used in unit tests.
     * @throws \RuntimeException
     * @return Config
     */
    public function loadSettings(bool $unitTest = false, $forceDevMode = false): Config
    {
        if ($this->config !== null) {
            return $this->config;
        }

        // Load environment variables from file if it exists.
        if (file_exists(Application::ROOT_DIR . '/.env')) {
            try {
                (new Dotenv())->load(Application::ROOT_DIR . '/.env');
            } catch (FormatException $e) {
                $this->logException($e);
            }
        }

        if (getenv('BRAVECORE_APP_ENV') === false) {
            throw new \RuntimeException(
                'BRAVECORE_APP_ENV environment variable is not defined. '.
                'You need to define environment variables for configuration '.
                'or load variables from a .env file (see .env.dist file).'
            );
        }

        if (getenv('BRAVECORE_APP_ENV') === self::ENV_PROD && ! $forceDevMode) {
            $this->env = self::ENV_PROD;
        } else {
            $this->env = self::ENV_DEV;
        }

        /** @noinspection PhpIncludeInspection */
        $settings = require self::ROOT_DIR . '/config/settings.php';

        if (getenv('PATH') !== false && strpos(getenv('PATH'), '/app/.heroku/php/') !== false) {
            /** @noinspection PhpIncludeInspection */
            $heroku = require self::ROOT_DIR . '/config/settings_heroku.php';
            $settings = array_replace_recursive($settings, $heroku);
        }

        if ($this->env === self::ENV_DEV) {
            /** @noinspection PhpIncludeInspection */
            $dev = require self::ROOT_DIR . '/config/settings_dev.php';
            $settings = array_replace_recursive($settings, $dev);
        }

        if ($unitTest) {
            /** @noinspection PhpIncludeInspection */
            $test = include self::ROOT_DIR . '/config/settings_tests.php';
            $settings = array_replace_recursive($settings, $test);
        }

        $this->config = new Config($settings);

        return $this->config;
    }

    public function runWebApp(): void
    {
        $this->runEnv = self::RUN_WEB;
        try {
            $this->getApp()->run();
        } catch (\Throwable $e) {
            $this->logException($e);
        }
    }

    /**
     * Creates the Slim app
     *
     * @param array $mocks Replaces dependencies in the DI container
     * @throws \Exception
     * @return App
     */
    public function getApp(array $mocks = []): App
    {
        $this->loadSettings();
        $this->buildContainer($mocks);
        $this->errorHandling();
        $this->sessionHandler();

        $app = Bridge::create($this->container);

        $this->addMiddleware($app);
        $this->registerRoutes($app);

        return $app;
    }

    public function runConsoleApp(): void
    {
        $this->runEnv = self::RUN_CONSOLE;
        try {
            $app = $this->getConsoleApp();
            $app->setCatchExceptions(false);
            $app->setAutoExit(false);
            $app->run();
        } catch (\Throwable $e) {
            $this->logException($e);
        }
    }

    /**
     * Creates the Symfony console app.
     *
     * @param array $mocks Replaces dependencies in the DI container
     * @throws \Exception
     * @return ConsoleApplication
     */
    public function getConsoleApp(array $mocks = []): ConsoleApplication
    {
        set_time_limit(0);

        $this->loadSettings();
        $this->buildContainer($mocks);
        $this->errorHandling();

        $console = new ConsoleApplication();

        $this->addCommands($console);

        return $console;
    }

    /**
     * @param App $app
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function addMiddleware(App $app): void
    {
        // Add middleware, last added are executed first.

        /** @noinspection PhpIncludeInspection */
        $app->add(new SecureRouteMiddleware(
            $this->container->get(ResponseFactoryInterface::class),
            include self::ROOT_DIR . '/config/security.php'
        ));

        $app->add(new RoleMiddleware($this->container->get(AppAuth::class), ['route_pattern' => ['/api/app']]));
        $app->add(new RoleMiddleware($this->container->get(UserAuth::class), ['route_pattern' => ['/api/user']]));

        $app->add(new NonBlockingSession([
            'name' => 'NCSESS',
            'secure' => $this->container->get(Config::class)['session']['secure'],
            'route_include_pattern' => ['/api/user', '/login'],
            'route_blocking_pattern' => ['/api/user/auth', '/login'],
        ]));

        // Add routing middleware after SecureRouteMiddleware, RoleMiddleware and NonBlockingSession,
        // so the `route` attribute is available from the ServerRequestInterface object
        $app->addRoutingMiddleware();

        if ($this->container->get(Config::class)['CORS']['allow_origin']) { // not false or empty string
            $app->add(new Cors(
                explode(',', $this->container->get(Config::class)['CORS']['allow_origin'])
            ));
        }

        $app->add(new BodyParams());

        // add error handler last
        $errorMiddleware = $app->addErrorMiddleware(false, true, true);
        $errorMiddleware->setDefaultErrorHandler(new Slim\ErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            $this->container->get(LoggerInterface::class),
            $this->env
        ));
    }

    /**
     * Builds the DI container.
     *
     * @throws \Exception
     */
    private function buildContainer(array $mocks = []): void
    {
        $containerBuilder = new ContainerBuilder();

        if ($this->env === self::ENV_PROD) {
            $containerBuilder->enableCompilation($this->config['di']['cache_dir']);
            if (SourceCache::isSupported()) {
                $containerBuilder->enableDefinitionCache();
            }
        }

        $containerBuilder->addDefinitions(\Neucore\Container::getDefinitions());

        $this->container = $containerBuilder->build();
        $this->container->set(Config::class, $this->config);

        foreach ($mocks as $class => $value) {
            $this->container->set($class, $value);
        }
    }

    /**
     * Register pdo session handler.
     *
     * (not for CLI)
     *
     * @throws DependencyException
     * @throws NotFoundException
     * @see https://symfony.com/doc/current/components/http_foundation/session_configuration.html
     */
    private function sessionHandler(): void
    {
        if (headers_sent()) { // should only be true for integration tests
            return;
        }

        ini_set('session.gc_maxlifetime', '1440'); // 24 minutes
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        $pdo = $this->container->get(EntityManagerInterface::class)->getConnection()->getWrappedConnection();
        $sessionHandler = new PdoSessionHandler($pdo, ['lock_mode' => PdoSessionHandler::LOCK_ADVISORY]);

        session_set_save_handler($sessionHandler, true);
    }

    /**
     * Setup error handling.
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function errorHandling(): void
    {
        error_reporting(E_ALL);

        // logs errors that are not handled by Slim
        ErrorHandler::register($this->container->get(LoggerInterface::class));

        // php settings
        ini_set('display_errors', '0');
        ini_set('log_errors', '0'); // all errors are logged with Monolog
    }

    private function registerRoutes(App $app): void
    {
        /** @noinspection PhpIncludeInspection */
        $routes = include self::ROOT_DIR . '/config/routes.php';

        foreach ($routes as $pattern => $conf) {
            if (isset($conf[0])) { // e. g. ['GET', 'method']
                $config = [$conf[0] => $conf[1]];
            } else { // e. g. ['GET' => 'method', 'POST' => 'method']
                $config = $conf;
            }
            foreach ($config as $method => $callable) {
                if ($method === 'GET') {
                    $app->get($pattern, $callable);
                } elseif ($method === 'POST') {
                    $app->post($pattern, $callable);
                } elseif ($method === 'DELETE') {
                    $app->delete($pattern, $callable);
                } elseif ($method === 'PUT') {
                    $app->put($pattern, $callable);
                }
            }
        }
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     * @throws LogicException
     */
    private function addCommands(ConsoleApplication $console): void
    {
        $console->add($this->container->get(MakeAdmin::class));
        $console->add($this->container->get(UpdateCharacters::class));
        $console->add($this->container->get(CheckTokens::class));
        $console->add($this->container->get(UpdatePlayerGroups::class));
        $console->add($this->container->get(SendInvalidTokenMail::class));
        $console->add($this->container->get(UpdateMemberTracking::class));
        $console->add($this->container->get(DoctrineFixturesLoad::class));
        $console->add($this->container->get(DBVerifySSL::class));
        $console->add($this->container->get(ClearCache::class));
        $console->add($this->container->get(CleanHttpCache::class));
        $console->add($this->container->get(RevokeToken::class));
    }

    private function logException(\Throwable $e): void
    {
        $log = null;
        if ($this->container instanceof ContainerInterface) {
            try {
                $log = $this->container->get(LoggerInterface::class);
            } catch (ContainerExceptionInterface $e) {
                // do nothing
            }
        }
        if ($log) {
            $log->error($e->getMessage(), ['exception' => $e]);
        } else {
            error_log((string) $e);
        }

        if ($this->runEnv === self::RUN_CONSOLE) {
            echo 'Error: ', $e->getMessage(), PHP_EOL;
            exit(1);
        }
    }
}
