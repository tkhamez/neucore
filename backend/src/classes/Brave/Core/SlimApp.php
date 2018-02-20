<?php
namespace Brave\Core;

use DI\ContainerBuilder;

/**
 * Slim application configured with PHP-DI.
 *
 * @see \DI\Bridge\Slim\App
 */
class SlimApp extends \Slim\App
{
    private $settings;

    public function __construct(array $settings, $env)
    {
        $containerBuilder = new ContainerBuilder;

        $reflector = new \ReflectionClass('DI\Bridge\Slim\App');
        $pathToConig = dirname($reflector->getFileName());
        $bridgeConfig = include $pathToConig . '/config.php';

        if ($env === Application::ENV_DEV) {
            // disable Slim’s error handling (cannot unset them from DI\Container)
            // https://www.slimframework.com/docs/v3/handlers/error.html
            unset($bridgeConfig['errorHandler']);
            unset($bridgeConfig['phpErrorHandler']);
        }

        $containerBuilder->addDefinitions($bridgeConfig);
        $containerBuilder->addDefinitions($settings);

        $container = $containerBuilder->build();

        parent::__construct($container);
    }
}
