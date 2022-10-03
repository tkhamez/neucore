<?php

declare(strict_types=1);

namespace Neucore;

use DI\Bridge\Slim\Bridge;
use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Source\SourceCache;
use Exception;
use Monolog\ErrorHandler;
use Neucore\Command\AssureMain;
use Neucore\Command\AutoAllowlist;
use Neucore\Command\CheckTokens;
use Neucore\Command\CleanHttpCache;
use Neucore\Command\ClearCache;
use Neucore\Command\DBVerifySSL;
use Neucore\Command\DoctrineFixturesLoad;
use Neucore\Command\MakeAdmin;
use Neucore\Command\RevokeToken;
use Neucore\Command\SendInvalidTokenMail;
use Neucore\Command\SendMissingCharacterMail;
use Neucore\Command\UpdateCharacters;
use Neucore\Command\UpdateCorporations;
use Neucore\Command\UpdateMemberTracking;
use Neucore\Command\UpdatePlayerGroups;
use Neucore\Command\UpdateServiceAccounts;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\SessionHandlerFactory;
use Neucore\Log\Context;
use Neucore\Middleware\Psr15\AppRequestCount;
use Neucore\Middleware\Psr15\Cors;
use Neucore\Middleware\Psr15\BodyParams;
use Neucore\Middleware\Psr15\CSRFToken;
use Neucore\Middleware\Psr15\HSTS;
use Neucore\Middleware\Psr15\RateLimitApp;
use Neucore\Middleware\Psr15\RateLimitGlobal;
use Neucore\Service\SessionData;
use Neucore\Slim\SessionMiddleware;
use Neucore\Service\AppAuth;
use Neucore\Service\Config;
use Neucore\Service\UserAuth;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;
use Throwable;
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
    private const ENV_PROD = 'prod';

    /**
     * @var string
     */
    private const ENV_DEV = 'dev';

    /**
     * @var string
     */
    private const RUN_WEB = 'web';

    /**
     * @var string
     */
    private const RUN_CONSOLE = 'console';

    /**
     * Path to application root directory.
     *
     * Does not have a trailing slash.
     *
     * @var string
     */
    public const ROOT_DIR = __DIR__ . '/..';

    /**
     * App setting from the config dir.
     */
    private ?Config $config = null;

    /**
     * The current environment.
     *
     * self::ENV_PROD or self::ENV_DEV
     */
    private string $env;

    /**
     * self::RUN_WEB or self::RUN_CONSOLE
     */
    private ?string $runEnv = null;

    private ?Container $container = null;

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
     * the environment variable NEUCORE_APP_ENV.
     *
     * Loads environment variables from a .env file if the environment variable
     * NEUCORE_APP_ENV does not exist.
     *
     * @param bool $unitTest Indicates if the app is running from a functional (integration) test.
     * @param bool $forceDevMode Only used in unit tests.
     * @throws RuntimeException
     * @return Config
     */
    public function loadSettings(bool $unitTest = false, bool $forceDevMode = false): Config
    {
        if ($this->config !== null) {
            return $this->config;
        }

        // Load environment variables from file if it exists.
        if (file_exists(Application::ROOT_DIR . '/.env')) {
            $dotEnv = new Dotenv();
            try {
                $dotEnv->load(Application::ROOT_DIR . '/.env');
            } catch (FormatException $e) {
                $this->logException($e);
            }
        } elseif (empty($_ENV)) {
            // It's empty if it's not included in variables_order
            $_ENV = getenv();
        }

        $appEnv = $_ENV['NEUCORE_APP_ENV'] ?? null;
        if ($appEnv === false) {
            $appEnv = $_ENV['BRAVECORE_APP_ENV'] ?? null;
        }
        if ($appEnv === false) {
            throw new RuntimeException(
                'NEUCORE_APP_ENV environment variable is not defined. '.
                'You need to define environment variables for configuration '.
                'or load variables from a .env file (see .env.dist file).'
            );
        }

        if ($appEnv === self::ENV_PROD && ! $forceDevMode) {
            $this->env = self::ENV_PROD;
        } else {
            $this->env = self::ENV_DEV;
        }

        /** @noinspection PhpIncludeInspection */
        $settings = require self::ROOT_DIR . '/config/settings.php';

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

    /**
     * @throws Exception
     */
    public function buildContainer(array $mocks = []): ContainerInterface
    {
        $this->loadSettings();
        $this->container = $this->createContainer($mocks);

        return $this->container;
    }

    public function runWebApp(): void
    {
        $this->runEnv = self::RUN_WEB;
        try {
            $this->getApp()->run();
        } catch (Throwable $e) {
            $this->logException($e);
        }
    }

    /**
     * Creates the Slim app
     *
     * @param array $mocks Replaces dependencies in the DI container
     * @throws Throwable
     * @return App
     */
    public function getApp(array $mocks = []): App
    {
        $this->buildContainer($mocks);
        $this->errorHandling();

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
        } catch (Throwable $e) {
            $this->logException($e);
        }
    }

    /**
     * Creates the Symfony console app.
     *
     * @param array $mocks Replaces dependencies in the DI container
     * @throws Throwable
     * @return ConsoleApplication
     */
    public function getConsoleApp(array $mocks = []): ConsoleApplication
    {
        set_time_limit(0);

        $this->buildContainer($mocks);
        $this->errorHandling();

        $console = new ConsoleApplication();

        $this->addCommands($console);

        return $console;
    }

    /**
     * @param App $app
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @phan-suppress PhanTypeInvalidThrowsIsInterface
     */
    private function addMiddleware(App $app): void
    {
        $config = $this->container->get(Config::class);

        // Add middleware, last added will be executed first.

        $app->add($this->container->get(RateLimitApp::class));
        $app->add($this->container->get(AppRequestCount::class));
        $app->add(new CSRFToken(
            $this->container->get(ResponseFactoryInterface::class),
            $this->container->get(SessionData::class),
            '/api/user'
        ));

        /** @noinspection PhpIncludeInspection */
        $app->add(new SecureRouteMiddleware(
            $this->container->get(ResponseFactoryInterface::class),
            include self::ROOT_DIR . '/config/security.php'
        ));

        $app->add(new RoleMiddleware($this->container->get(AppAuth::class), ['route_pattern' => ['/api/app']]));
        $app->add(new RoleMiddleware(
            $this->container->get(UserAuth::class),
            ['route_pattern' => ['/api/user', '/plugin']]
        ));

        $hsts = $config['HSTS']['max_age'];
        if (trim($hsts) !== '') {
            $app->add(new HSTS((int)$hsts));
        }

        $app->add(new SessionMiddleware(
            $this->container->get(SessionHandlerFactory::class),
            [
                SessionMiddleware::OPTION_NAME                   => 'neucore',
                SessionMiddleware::OPTION_SECURE                 => $config['session']['secure'],
                SessionMiddleware::OPTION_SAME_SITE              => $config['session']['same_site'],
                SessionMiddleware::OPTION_ROUTE_INCLUDE_PATTERN  => ['/api/user', '/login', '/plugin'],
                SessionMiddleware::OPTION_ROUTE_BLOCKING_PATTERN => ['/api/user/auth', '/login', '/plugin'],
            ]
        ));

        // Add routing middleware after SecureRouteMiddleware, RoleMiddleware and NonBlockingSession,
        // so the `route` attribute is available from the ServerRequestInterface object
        $app->addRoutingMiddleware();

        $app->add($this->container->get(BodyParams::class));

        // Add the global rate limit before the database connection is used.
        $app->add($this->container->get(RateLimitGlobal::class));

        $errorMiddleware = $app->addErrorMiddleware(false, true, true);
        $errorMiddleware->setDefaultErrorHandler(new Slim\ErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            $this->container->get(LoggerInterface::class)
        ));

        // add CORS last, so it is executed first, especially before the error handler.
        if ($config['CORS']['allow_origin']) { // not false or empty string
            $app->add(new Cors(
                $this->container->get(ResponseFactoryInterface::class),
                explode(',', $config['CORS']['allow_origin'])
            ));
        }
    }

    /**
     * Builds the DI container.
     *
     * @throws Exception
     */
    private function createContainer(array $mocks = []): Container
    {
        $containerBuilder = new ContainerBuilder();

        if ($this->env === self::ENV_PROD && $this->config !== null) {
            $containerBuilder->enableCompilation($this->config['di']['cache_dir']);
            if (SourceCache::isSupported()) {
                $containerBuilder->enableDefinitionCache();
            }
        }

        $containerBuilder->addDefinitions(\Neucore\Container::getDefinitions());

        $container = $containerBuilder->build();
        $container->set(Config::class, $this->config);

        foreach ($mocks as $class => $value) {
            $container->set($class, $value);
        }

        return $container;
    }

    /**
     * Setup error handling.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @phan-suppress PhanTypeInvalidThrowsIsInterface
     */
    private function errorHandling(): void
    {
        // PHP settings.
        $path = realpath($this->container->get(Config::class)['monolog']['path']);
        if ($path !== false && is_writable($path)) {
            ini_set('error_log', $path . '/error.log');
        }
        ini_set('display_errors', '0');
        ini_set('log_errors', '0'); // all errors are logged with Monolog
        if ($this->config) {
            error_reporting((int)$this->config['error_reporting']);
        }

        // Logs errors that are not handled by Slim and for CLI
        ErrorHandler::register($this->container->get(LoggerInterface::class));
    }

    private function registerRoutes(App $app): void
    {
        /** @noinspection PhpIncludeInspection */
        $routes = include self::ROOT_DIR . '/config/routes.php';

        foreach ($routes as $pattern => $configuration) {
            if (isset($configuration[0])) { // e. g. ['GET', 'method']
                $routeConfig = [$configuration[0] => $configuration[1]];
            } else { // e. g. ['GET' => 'method', 'POST' => 'method']
                $routeConfig = $configuration;
            }
            foreach ($routeConfig as $method => $callable) {
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
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws LogicException
     * @phan-suppress PhanTypeInvalidThrowsIsInterface
     */
    private function addCommands(ConsoleApplication $console): void
    {
        $console->add($this->container->get(MakeAdmin::class));
        $console->add($this->container->get(UpdateCharacters::class));
        $console->add($this->container->get(UpdateCorporations::class));
        $console->add($this->container->get(CheckTokens::class));
        $console->add($this->container->get(UpdatePlayerGroups::class));
        $console->add($this->container->get(SendInvalidTokenMail::class));
        $console->add($this->container->get(SendMissingCharacterMail::class));
        $console->add($this->container->get(UpdateMemberTracking::class));
        $console->add($this->container->get(DoctrineFixturesLoad::class));
        $console->add($this->container->get(DBVerifySSL::class));
        $console->add($this->container->get(ClearCache::class));
        $console->add($this->container->get(CleanHttpCache::class));
        $console->add($this->container->get(RevokeToken::class));
        $console->add($this->container->get(AutoAllowlist::class));
        $console->add($this->container->get(AssureMain::class));
        $console->add($this->container->get(UpdateServiceAccounts::class));
    }

    private function logException(Throwable $e): void
    {
        $log = null;
        if ($this->container instanceof ContainerInterface) {
            try {
                $log = $this->container->get(LoggerInterface::class);
            } catch (ContainerExceptionInterface | NotFoundExceptionInterface $e) {
                // do nothing
            }
        }
        if ($log) {
            $log->error($e->getMessage(), [Context::EXCEPTION => $e]);
        } else {
            error_log((string) $e);
        }

        if ($this->runEnv === self::RUN_CONSOLE) {
            echo 'Error: ', $e->getMessage(), PHP_EOL;
            exit(1);
        }
    }
}
