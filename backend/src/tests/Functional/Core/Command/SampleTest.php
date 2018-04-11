<?php

namespace Tests\Functional\Core\Command;

use Brave\Core\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SampleTest extends \PHPUnit\Framework\TestCase
{
    public function testRunConsole()
    {
        $app = new Application();
        $console = $app->getConsoleApp();

        $command = $console->find('sample');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'arg'     => 'a1',
            '--opt'   => 'o1',
        ]);
        $output = $commandTester->getDisplay();

        $this->assertSame("Sample command with arg: a1, opt: o1 done.\n", $output);
    }
}
