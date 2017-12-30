<?php
namespace Brave\Core;

use Brave\Core\Api\App\Controller\AppInfo;
use Brave\Core\Api\User\Controller\UserInfo;
use Brave\Core\OAuth\EveSsoController;
use Brave\Core\OAuth\EveSsoService;
use Brave\Core\Service\AppAuthService;
use Brave\Core\Service\UserAuthService;
use Brave\Middleware\Cors;
use Brave\Slim\Handlers\Error;
use Brave\Slim\Handlers\PhpError;
use Brave\Slim\Role\AuthRoleMiddleware;
use Brave\Slim\Role\SecureRouteMiddleware;
use Brave\Slim\Session\NonBlockingSessionMiddleware;

use DI\Container;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;

use GuzzleHttp\Client;

use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use Slim\App;

use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;

use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * App bootstrapping
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
     * self::PROD or self::DEV
     *
     * @var string
     */
    private $env;

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
     * @return array
     */
    public function settings()
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

        if ($_SERVER['BRAVECORE_APP_ENV'] !== 'prod') {
            $this->env = Application::ENV_DEV;
        } else {
            $this->env = Application::ENV_PROD;
        }

        $this->settings = require Application::ROOT_DIR . '/config/settings.php';

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

        return $this->settings;
    }

    /**
     * Creates the Slim app (for unit tests)
     *
     * @return App
     */
    public function getApp(bool $withMiddleware)
    {
        $app = $this->app();

        $this->dependencies($app->getContainer());
        if ($withMiddleware) {
            $this->middleware($app);
        }
        $this->routes($app);

        return $app;
    }

    /**
     * Run the web application.
     */
    public function run()
    {
        $app = $this->app();

        // Set up dependencies
        $this->dependencies($app->getContainer());

        // Register middleware
        $this->middleware($app);

        // Register routes
        $this->routes($app);

        // Run app
        $app->run();
    }

    /**
     * Run the console application.
     */
    public function runConsole()
    {
        set_time_limit(0);

        $app = $this->app();

        $this->dependencies($app->getContainer());
        $this->middleware($app);

        $console = new ConsoleApplication();

        $console->register('my-command')
            ->setDefinition(array(
                // new InputOption('some-option', null, InputOption::VALUE_NONE, 'Some help'),
            ))
            ->setDescription('My command description')
            ->setCode(function(InputInterface $input, OutputInterface $output) use ($app) {
                // do something
            });

        $console->run();
    }

    /**
     *
     * @return App
     */
    private function app()
    {
        $this->settings();

        if ($this->env === self::ENV_DEV) {
            umask(0000);
        } else {
            umask(0002);
        }

        // Instantiate the app
        $app = new SlimApp($this->settings);

        return $app;
    }

    /**
     * Set up dependencies and error handling
     *
     * @param Container $container
     */
    private function dependencies(Container $container)
    {
        // Doctrine
        $container->set(EntityManagerInterface::class, function ($c) {
            $conf = $c->get('config')['doctrine'];
            $config = Setup::createAnnotationMetadataConfiguration(
                $conf['meta']['entity_path'],
                $conf['meta']['dev_mode'],
                $conf['meta']['proxy_dir']
            );
            return EntityManager::create($conf['connection'], $config);
        });

        // Monolog
        $container->set(LoggerInterface::class, function (ContainerInterface $c) {
            $conf = $c->get('config')['monolog'];
            $logger = new Logger($conf['name']);
            $logger->pushHandler(new StreamHandler($conf['path'], $conf['level']));
            return $logger;
        });

        // extend Slim's error handler
        $container->set('errorHandler', function ($c) {
            return new Error($c->get('settings')['displayErrorDetails'], $c->get(LoggerInterface::class));
        });

        // extend Slim's php error handler
        $container->set('phpErrorHandler', function ($c) {
            return new PhpError($c->get('settings')['displayErrorDetails'], $c->get(LoggerInterface::class));
        });

        // eve soo service
        $container->set(EveSsoService::class, new EveSsoService(
            $container->get('config')['eve'], new Client(), $container->get(LoggerInterface::class)
        ));

        // error handling
        ini_set('display_errors', 0);
        error_reporting(E_ALL);
        if ($this->env === self::ENV_DEV) {
            $whoops = new Run();
            $whoops->pushHandler(new PrettyPageHandler());
            $whoops->register();
        } else {
            ErrorHandler::register($container->get(LoggerInterface::class));
        }
    }

    private function middleware(App $app)
    {
        $c = $app->getContainer();

        // Add middleware, last added are executed first.

        $app->add(new SecureRouteMiddleware([
            // add necessary exceptions to /api/user route
            '/api/user/auth/login' => ['role.anonymous', 'role.user'],
            '/api/user/auth/callback' => ['role.anonymous', 'role.user'],
            '/api/user/auth/result' => ['role.anonymous', 'role.user'],

            '/api/user' => ['role.user'],
            '/api/app' => ['role.app']
        ]));

        $app->add(new AuthRoleMiddleware($c->get(AppAuthService::class), ['route_pattern' => ['/api/app']]));
        $app->add(new AuthRoleMiddleware($c->get(UserAuthService::class), ['route_pattern' => ['/api/user']]));

        $app->add(new NonBlockingSessionMiddleware([
            'name' => 'BCSESS',
            'route_include_pattern' => ['/api/user'],
            'route_blocking_pattern' => [
                '/api/user/auth/login',
                '/api/user/auth/callback',
                '/api/user/auth/result',
                '/api/user/auth/logout'
            ],
        ]));

        $app->add(new Cors($c->get('config')['CORS']['allow_origin']));
    }

    private function routes(App $app)
    {
        $app->group('/api/app', function () use ($app) {
            $app->get('/info', AppInfo::class)->setName('api_app_info');
        });

        $app->group('/api/user', function () use ($app) {
            $app->group('/auth', function () use ($app) {
                $app->get('/login', EveSsoController::class . '::login')->setName('api_user_auth_login');
                $app->get('/callback', EveSsoController::class . '::callback')->setName('api_user_auth_callback');
                $app->get('/result', EveSsoController::class . '::result')->setName('api_user_auth_result');
                $app->get('/logout', EveSsoController::class . '::logout')->setName('api_user_auth_logout');
            });

            $app->get('/info', UserInfo::class)->setName('api_user_info');
        });
    }
}
