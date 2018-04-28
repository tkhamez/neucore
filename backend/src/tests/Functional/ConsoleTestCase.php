<?php declare(strict_types=1);

namespace Tests\Functional;

use Brave\Core\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Runs the application.
 */
class ConsoleTestCase extends \PHPUnit\Framework\TestCase
{
    protected function runConsoleApp($name, $input = [], array $mocks = [])
    {
        $app = new Application();
        $app->loadSettings(true);
        $slimApp = $app->getApp(false, false); // no middleware, without routes

        // change dependencies in container
        /* @var $c \DI\Container */
        $c = $slimApp->getContainer();
        foreach ($mocks as $class => $obj) {
            $c->set($class, $obj);
        }

        // add middleware (gets objects from container)
        $app->addMiddleware($slimApp);

        $console = $app->getConsoleApp($slimApp);

        $command = $console->find($name);
        $commandTester = new CommandTester($command);
        $commandTester->execute($input);

        return $commandTester->getDisplay();
    }
}
