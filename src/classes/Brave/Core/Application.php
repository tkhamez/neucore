<?php
namespace Brave\Core;

use Brave\Core\Controller\HomeController;
use Brave\Core\Handlers\Error;
use Brave\Core\Handlers\PhpError;

use DI\Container;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;

use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use Slim\App;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;

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
	 * the environment variable APP_ENV.
	 *
	 * Loads environment variables from a .env file if the environment variable
	 * APP_ENV does not exist and the composer package symfony/dotenv is available.
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
		if (! isset($_SERVER['APP_ENV']) && class_exists(Dotenv::class)) {
			(new Dotenv())->load(Application::ROOT_DIR . '/.env');
		}

		if (! isset($_SERVER['APP_ENV'])) {
			throw new \RuntimeException(
				'APP_ENV environment variable is not defined. '.
				'You need to define environment variables for configuration '.
				'or load variables from a .env file (see .env.dist file).'
			);
		}

		if ($_SERVER['APP_ENV'] !== 'prod') {
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

		//session_start();

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
		// Twig
		$container->set(Twig::class, function (ContainerInterface $c) {
			$conf = $c->get('config')['twig'];
			$view = new Twig($conf['template_path'], [
				'cache' => $conf['cache'],
			]);

			// Instantiate and add Slim specific extension
			$view->addExtension(new TwigExtension($c->get('router'), $c->get('request')->getUri()));

			return $view;
		});

	    // Doctrine
	    $container->set(EntityManager::class, function ($c) {
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
		// Application middleware

		// e.g: $app->add(new \Slim\Csrf\Guard);
	}

	private function routes(App $app)
	{
		$app->get('/[{name}]', HomeController::class . '::home')->setName('home');
	}
}
