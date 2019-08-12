<?php declare(strict_types=1);

namespace Neucore;

use Brave\Sso\Basics\AuthenticationProvider;
use DI\Container;
use DI\ContainerBuilder;
use DI\Definition\Source\SourceCache;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\Common\Persistence\ObjectManager;
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
use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogglyFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Neucore\Command\CheckTokens;
use Neucore\Command\CleanHttpCache;
use Neucore\Command\ClearCache;
use Neucore\Command\DBVerifySSL;
use Neucore\Command\DoctrineFixturesLoad;
use Neucore\Command\MakeAdmin;
use Neucore\Command\RevokeToken;
use Neucore\Command\SendAccountDisabledMail;
use Neucore\Command\UpdateCharacters;
use Neucore\Command\UpdateMemberTracking;
use Neucore\Command\UpdatePlayerGroups;
use Neucore\Factory\ResponseFactory;
use Neucore\Log\FluentdFormatter;
use Neucore\Log\GelfMessageFormatter;
use Neucore\Middleware\GuzzleEsiHeaders;
use Neucore\Middleware\PsrCors;
use Neucore\Service\AppAuth;
use Neucore\Service\Config;
use Neucore\Service\UserAuth;
use Neucore\Slim\Handlers\Error;
use Neucore\Slim\Handlers\PhpError;
use Neucore\Slim\Session\NonBlockingSessionMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
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
     * Path to application root directory.
     *
     * Does not have a trailing slash.
     *
     * @var string
     */
    const ROOT_DIR = __DIR__ . '/../..';

    /**
     * Slim setting from the config dir.
     *
     * @var array
     */
    private $slimSettings;

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
                error_log((string) $e);
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

        $this->config = new Config($settings['config']);

        unset($settings['config']);
        $this->slimSettings = $settings;

        return $this->config;
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

        $app = new App($this->container);

        $this->addMiddleware($app);
        $this->registerRoutes($app);

        return $app;
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
        $this->buildContainer($mocks, false);
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
        $security = include self::ROOT_DIR . '/config/security.php';
        $app->add(new SecureRouteMiddleware($security));

        $app->add(new RoleMiddleware($this->container->get(AppAuth::class), ['route_pattern' => ['/api/app']]));
        $app->add(new RoleMiddleware($this->container->get(UserAuth::class), ['route_pattern' => ['/api/user']]));

        $app->add(new NonBlockingSessionMiddleware([
            'name' => 'NCSESS',
            'secure' => $this->container->get(Config::class)['session']['secure'],
            'route_include_pattern' => ['/api/user', '/login'],
            'route_blocking_pattern' => ['/api/user/auth', '/login'],
        ]));

        if ($this->container->get(Config::class)['CORS']['allow_origin']) { // not false or empty string
            $app->add(new PsrCors(explode(',', $this->container->get(Config::class)['CORS']['allow_origin'])));
        }
    }

    /**
     * Builds the DI container.
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function buildContainer(array $mocks = [], $addSlimConfig = true): void
    {
        $containerBuilder = new ContainerBuilder();

        if ($this->env === self::ENV_PROD) {
            $containerBuilder->enableCompilation($this->config['di']['cache_dir']);
            if (SourceCache::isSupported()) {
                $containerBuilder->enableDefinitionCache();
            }
        }

        if ($addSlimConfig) {
            // include config.php from php-di/slim-bridge
            $reflector = new \ReflectionClass(\DI\Bridge\Slim\App::class);
            /** @noinspection PhpIncludeInspection */
            $bridgeConfig = include dirname((string) $reflector->getFileName()) . '/config.php';

            $containerBuilder->addDefinitions($bridgeConfig);
        }

        $containerBuilder->addDefinitions($this->slimSettings);
        $containerBuilder->addDefinitions($this->getDependencies());

        $this->container = $containerBuilder->build();
        $this->container->set(Config::class, $this->config);

        foreach ($mocks as $class => $value) {
            $this->container->set($class, $value);
        }
    }

    /**
     * Definitions for the DI container.
     */
    private function getDependencies()
    {
        return [

            // Doctrine
            EntityManagerInterface::class => function (ContainerInterface $c) {
                $conf = $c->get(Config::class)['doctrine'];
                $config = Setup::createAnnotationMetadataConfiguration(
                    $conf['meta']['entity_paths'],
                    $conf['meta']['dev_mode'],
                    $conf['meta']['proxy_dir'],
                    null,
                    false
                );
                /* @phan-suppress-next-line PhanDeprecatedFunction */
                /** @noinspection PhpDeprecationInspection */
                AnnotationRegistry::registerLoader('class_exists');
                if ((string) $conf['driver_options']['mysql_ssl_ca'] !== '' &&
                    (
                        ! $conf['driver_options']['mysql_verify_server_cert'] ||
                        is_file($conf['driver_options']['mysql_ssl_ca'])
                    )
                ) {
                    $conf['connection']['driverOptions'] = [
                        \PDO::MYSQL_ATTR_SSL_CA => $conf['driver_options']['mysql_ssl_ca'],
                        \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT =>
                            (bool) $conf['driver_options']['mysql_verify_server_cert'],
                    ];
                }
                return EntityManager::create($conf['connection'], $config);
            },
            ObjectManager::class => function (ContainerInterface $c) {
                return $c->get(EntityManagerInterface::class);
            },

            // EVE OAuth
            GenericProvider::class => function (ContainerInterface $c) {
                $conf = $c->get(Config::class)['eve'];
                $urls = $conf['datasource'] === 'singularity' ? $conf['oauth_urls_sisi'] : $conf['oauth_urls_tq'];
                return new GenericProvider([
                    'clientId'                => $conf['client_id'],
                    'clientSecret'            => $conf['secret_key'],
                    'redirectUri'             => $conf['callback_url'],
                    'urlAuthorize'            => $urls['authorize'],
                    'urlAccessToken'          => $urls['token'],
                    'urlResourceOwnerDetails' => $urls['verify'],
                ], [
                    'httpClient' => $c->get(ClientInterface::class)
                ]);
            },
            AuthenticationProvider::class => function (ContainerInterface $c) {
                $conf = $c->get(Config::class)['eve'];
                $urls = $conf['datasource'] === 'singularity' ? $conf['oauth_urls_sisi'] : $conf['oauth_urls_tq'];
                return new AuthenticationProvider($c->get(GenericProvider::class), [], $urls['jwks']);
            },

            // Monolog
            LoggerInterface::class => function (ContainerInterface $c) {
                $path = $c->get(Config::class)['monolog']['path'];
                $rotation = $c->get(Config::class)['monolog']['rotation'];
                if (strpos($path, 'php://') === false) {
                    if (! is_writable($path)) {
                        throw new \Exception('The log directory "' . $path . '" must be writable by the web server.');
                    }
                    $path .= '/app-' . (PHP_SAPI === 'cli' ? 'cli-' : '') .
                        ($rotation === 'daily' ? date('Ymd') : (
                            $rotation === 'monthly' ? date('Ym') : date('o\wW')
                        )) . '.log';
                }
                $format = $c->get(Config::class)['monolog']['format'];
                if ($format === 'fluentd') {
                    $formatter = new FluentdFormatter();
                } elseif ($format === 'gelf') {
                    $formatter = new GelfMessageFormatter();
                } elseif ($format === 'html') {
                    $formatter = new HtmlFormatter();
                } elseif ($format === 'json') {
                    $formatter = new JsonFormatter();
                    $formatter->includeStacktraces(true);
                } elseif ($format === 'loggly') {
                    $formatter = new LogglyFormatter(JsonFormatter::BATCH_MODE_JSON, true);
                    $formatter->includeStacktraces(true);
                } elseif ($format === 'logstash') {
                    $formatter = new LogstashFormatter('Neucore');
                } else { // multiline or line
                    $formatter = new LineFormatter();
                    $formatter->ignoreEmptyContextAndExtra(true);
                    if ($format === 'multiline') {
                        $formatter->includeStacktraces(true);
                    }
                }
                $handler = (new StreamHandler($path, Logger::DEBUG))->setFormatter($formatter);
                return (new Logger('app'))->pushHandler($handler);
            },

            // Guzzle
            ClientInterface::class => function (ContainerInterface $c) {
                /*$debugFunc = function (\Psr\Http\Message\MessageInterface $r) use ($c) {
                    if ($r instanceof \Psr\Http\Message\RequestInterface) {
                        $c->get(LoggerInterface::class)->debug($r->getMethod() . ' ' . $r->getUri());
                    } elseif ($r instanceof \Psr\Http\Message\ResponseInterface) {
                        $c->get(LoggerInterface::class)->debug('Status Code: ' . $r->getStatusCode());
                    }
                    $headers = [];
                    foreach ($r->getHeaders() as $name => $val) {
                        $headers[$name] = $val[0];
                    }
                    #$c->get(LoggerInterface::class)->debug(print_r($headers, true));
                    return $r;
                };*/

                $stack = HandlerStack::create();
                #$stack->push(\GuzzleHttp\Middleware::mapRequest($debugFunc));
                $stack->push(
                    new CacheMiddleware(
                        new PrivateCacheStrategy(
                            new DoctrineCacheStorage(
                                new FilesystemCache($c->get(Config::class)['guzzle']['cache']['dir'])
                            )
                        )
                    ),
                    'cache'
                );
                $stack->push($c->get(GuzzleEsiHeaders::class));
                #$stack->push(\GuzzleHttp\Middleware::mapResponse($debugFunc));

                return new Client([
                    'handler' => $stack,
                    'headers' => [
                        'User-Agent' => $c->get(Config::class)['guzzle']['user_agent'],
                    ],
                ]);
            },

            // Slim
            ResponseInterface::class => function () {
                return (new ResponseFactory())->createResponse();
            },
            'errorHandler' => function (ContainerInterface $c) {
                return new Error($c->get('settings')['displayErrorDetails'], $c->get(LoggerInterface::class));
            },
            'phpErrorHandler' => function (ContainerInterface $c) {
                return new PhpError($c->get('settings')['displayErrorDetails'], $c->get(LoggerInterface::class));
            },
        ];
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
        $console->add($this->container->get(SendAccountDisabledMail::class));
        $console->add($this->container->get(UpdateMemberTracking::class));
        $console->add($this->container->get(DoctrineFixturesLoad::class));
        $console->add($this->container->get(DBVerifySSL::class));
        $console->add($this->container->get(ClearCache::class));
        $console->add($this->container->get(CleanHttpCache::class));
        $console->add($this->container->get(RevokeToken::class));
    }
}
