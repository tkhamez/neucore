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
        try {
            $container = $app->getContainer();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        foreach ($mocks as $class => $obj) {
            $container->set($class, $obj);
        }

        try {
            $console = $app->getConsoleApp();
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $command = $console->find($name);
        $commandTester = new CommandTester($command);
        $commandTester->execute($input);

        return $commandTester->getDisplay();
    }
}
