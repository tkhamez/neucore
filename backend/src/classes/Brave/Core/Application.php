<?php declare(strict_types=1);

namespace Brave\Core;

use Brave\Core\Command\MakeAdmin;
use Brave\Core\Command\SendAccountDisabledMail;
use Brave\Core\Command\UpdateCharacters;
use Brave\Core\Command\UpdateMemberTracking;
use Brave\Core\Command\UpdatePlayerGroups;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\AppAuth;
use Brave\Core\Service\AutoGroupAssignment;
use Brave\Core\Service\Account;
use Brave\Core\Service\Config;
use Brave\Core\Service\EsiData;
use Brave\Core\Service\EveMail;
use Brave\Core\Service\MemberTracking;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use Brave\Core\Service\UserAuth;
use Brave\Middleware\Cors;
use Brave\Slim\Handlers\Error;
use Brave\Slim\Handlers\PhpError;
use Brave\Slim\Session\NonBlockingSessionMiddleware;
use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Source\SourceCache;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use League\OAuth2\Client\Provider\GenericProvider;
use Monolog\ErrorHandler;
use Monolog\Formatter\LineFormatter;
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
     * @throws \RuntimeException
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

        /** @noinspection PhpIncludeInspection */
        $this->settings = require self::ROOT_DIR . '/config/settings.php';

        if (getenv('PATH') !== false && strpos(getenv('PATH'), '/app/.heroku/php/') !== false) {
            /** @noinspection PhpIncludeInspection */
            $heroku = require self::ROOT_DIR . '/config/settings_heroku.php';
            $this->settings = array_replace_recursive($this->settings, $heroku);
        } elseif (PHP_SAPI === 'cli') {
            /** @noinspection PhpIncludeInspection */
            $cli = require self::ROOT_DIR . '/config/settings_cli.php';
            $this->settings = array_replace_recursive($this->settings, $cli);
        }

        if ($this->env === self::ENV_DEV) {
            /** @noinspection PhpIncludeInspection */
            $dev = require self::ROOT_DIR . '/config/settings_dev.php';
            $this->settings = array_replace_recursive($this->settings, $dev);
        }

        if ($unitTest) {
            /** @noinspection PhpIncludeInspection */
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

        /** @noinspection PhpIncludeInspection */
        $security = include self::ROOT_DIR . '/config/security.php';
        $app->add(new SecureRouteMiddleware($security));

        $app->add(new RoleMiddleware($this->container->get(AppAuth::class), ['route_pattern' => ['/api/app']]));
        $app->add(new RoleMiddleware($this->container->get(UserAuth::class), ['route_pattern' => ['/api/user']]));

        $app->add(new NonBlockingSessionMiddleware([
            'name' => 'BCSESS',
            'secure' => $this->env === self::ENV_PROD,
            'route_include_pattern' => ['/api/user', '/login'],
            'route_blocking_pattern' => ['/api/user/auth', '/login'],
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
        /** @noinspection PhpIncludeInspection */
        $bridgeConfig = include dirname($reflector->getFileName()) . '/config.php';

        $containerBuilder = new ContainerBuilder();

        if ($this->env === self::ENV_PROD) {
            $containerBuilder->enableCompilation($this->settings['config']['di']['cache_dir']);
            if (SourceCache::isSupported()) {
                $containerBuilder->enableDefinitionCache();
            }
        }

        $containerBuilder->addDefinitions($bridgeConfig);
        $containerBuilder->addDefinitions($this->settings);
        $containerBuilder->addDefinitions($this->getDependencies());

        $this->container = $containerBuilder->build();
    }

    /**
     * Definitions for the DI container.
     */
    private function getDependencies()
    {
        return [
            // Configuration class
            Config::class => function (ContainerInterface $c) {
                return new Config($c->get('config'));
            },

            // Doctrine
            EntityManagerInterface::class => function (ContainerInterface $c) {
                $conf = $c->get('config')['doctrine'];
                $config = Setup::createAnnotationMetadataConfiguration(
                    $conf['meta']['entity_paths'],
                    $conf['meta']['dev_mode'],
                    $conf['meta']['proxy_dir']
                );
                return EntityManager::create($conf['connection'], $config);
            },

            // EVE OAuth
            GenericProvider::class => function (ContainerInterface $c) {
                $domain = $c->get('config')['eve']['datasource'] === 'singularity' ?
                    'sisilogin.testeveonline.com' :
                    'login.eveonline.com';
                return new GenericProvider([
                    'clientId'                => $c->get('config')['eve']['client_id'],
                    'clientSecret'            => $c->get('config')['eve']['secret_key'],
                    'redirectUri'             => $c->get('config')['eve']['callback_url'],
                    'urlAuthorize'            => 'https://' . $domain . '/oauth/authorize',
                    'urlAccessToken'          => 'https://' . $domain . '/oauth/token',
                    'urlResourceOwnerDetails' => 'https://' . $domain . '/oauth/verify',
                ]);
            },

            // Monolog
            LoggerInterface::class => function (ContainerInterface $c) {
                $conf = $c->get('config')['monolog'];
                if (strpos($conf['path'], 'php://') === false) {
                    $dir = realpath(dirname($conf['path']));
                    if (! is_writable($dir)) {
                        throw new \Exception('The log directory ' . $dir . ' must be writable by the web server.');
                    }
                }
                $formatter = new LineFormatter();
                $formatter->allowInlineLineBreaks();
                $handler = (new StreamHandler($conf['path'], $conf['level']))->setFormatter($formatter);
                $logger = (new Logger($conf['name']))->pushHandler($handler);
                return $logger;
            },

            // Guzzle
            ClientInterface::class => function (ContainerInterface $c) {
                /*$debugFunc = function (\Psr\Http\Message\MessageInterface $r) use ($c) {
                    if ($r instanceof \Psr\Http\Message\ResponseInterface) {
                        $c->get(LoggerInterface::class)->debug('Status Code: ' . $r->getStatusCode());
                    }
                    $headers = [];
                    foreach ($r->getHeaders() as $name => $val) {
                        $headers[$name] = $val[0];
                    }
                    $c->get(LoggerInterface::class)->debug(print_r($headers, true));
                    return $r;
                };*/
                $stack = HandlerStack::create();
                #$stack->push(\GuzzleHttp\Middleware::mapRequest($debugFunc));
                #$stack->push(\GuzzleHttp\Middleware::mapResponse($debugFunc));
                $stack->push(
                    new CacheMiddleware(
                        new PrivateCacheStrategy(
                            new DoctrineCacheStorage(
                                new FilesystemCache($c->get('config')['guzzle']['cache']['dir'])
                            )
                        )
                    ),
                    'cache'
                );
                #$stack->push(\GuzzleHttp\Middleware::mapRequest($debugFunc));
                #$stack->push(\GuzzleHttp\Middleware::mapResponse($debugFunc));
                return new Client(['handler' => $stack]);
            },

            // Extend Slim's error and php error handler to log all errors with monolog.
            'errorHandler' => function (Container $c) {
                return new Error($c->get('settings')['displayErrorDetails'], $c->get(LoggerInterface::class));
            },
            'phpErrorHandler' => function (Container $c) {
                return new PhpError($c->get('settings')['displayErrorDetails'], $c->get(LoggerInterface::class));
            },
        ];
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
        error_reporting(E_ALL);

        // logs errors that are not handled by Slim
        ErrorHandler::register($this->container->get(LoggerInterface::class));

        // php settings
        ini_set('display_errors', '0');
        ini_set('log_errors', '0'); // all errors are logged with Monolog

        // log for exceptions caught in web/app.php and bin/console - path was already checked to be writable
        ini_set('error_log', $this->container->get('config')['monolog']['path']);
    }

    private function registerRoutes(App $app): void
    {
        /** @noinspection PhpIncludeInspection */
        $routes = include self::ROOT_DIR . '/config/routes.php';

        foreach ($routes as $pattern => $conf) {
            #if (is_array($conf[0])) {
            #    $app->map($conf[0], $pattern, $conf[1]);
            #    continue;
            #}

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
            $this->container->get(Account::class),
            $this->container->get(OAuthToken::class),
            $this->container->get(ObjectManager::class)
        ));

        $console->add(new UpdatePlayerGroups(
            $this->container->get(RepositoryFactory::class),
            $this->container->get(AutoGroupAssignment::class),
            $this->container->get(ObjectManager::class)
        ));

        $console->add(new SendAccountDisabledMail(
            $this->container->get(EveMail::class),
            $this->container->get(RepositoryFactory::class),
            $this->container->get(ObjectManager::class)
        ));

        $console->add(new UpdateMemberTracking(
            $this->container->get(RepositoryFactory::class),
            $this->container->get(MemberTracking::class),
            $this->container->get(EsiData::class)
        ));
    }
}
