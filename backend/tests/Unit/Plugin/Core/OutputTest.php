<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin\Core;

use Neucore\Plugin\Core\Output;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class OutputTest extends TestCase
{
    public function testWrite()
    {
        $buffer = new BufferedOutput();
        $output = new Output($buffer);

        $output->write('Test');

        $this->assertSame('Test', $buffer->fetch());
    }

    public function testWriteLine()
    {
        $buffer = new BufferedOutput();
        $output = new Output($buffer);

        $output->writeLine('Test');

        $this->assertSame('Test' . PHP_EOL, $buffer->fetch());
    }
}
