<?php
namespace Tests\Functional\Core;

use Brave\Core\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ApplicationTest extends \PHPUnit\Framework\TestCase
{

    public function testRunConsole()
    {
        $app = new Application();
        $console = $app->getConsoleApp();

        $command = $console->find('my-command');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName()
        ]);
        $output = $commandTester->getDisplay();

        $this->assertSame("All done.\n", $output);
    }
}
