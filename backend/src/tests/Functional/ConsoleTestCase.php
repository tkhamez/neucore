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

        // change dependencies in container
        $container = $app->getContainer();
        foreach ($mocks as $class => $obj) {
            $container->set($class, $obj);
        }

        $console = $app->getConsoleApp();

        $command = $console->find($name);
        $commandTester = new CommandTester($command);
        $commandTester->execute($input);

        return $commandTester->getDisplay();
    }
}
