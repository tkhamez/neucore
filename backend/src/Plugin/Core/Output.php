<?php

declare(strict_types=1);

namespace Neucore\Plugin\Core;

use Symfony\Component\Console\Output\OutputInterface;

class Output implements \Neucore\Plugin\Core\OutputInterface
{
    private OutputInterface $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public function write(string $message): void
    {
        $this->output->write($message);
    }

    public function writeLine(string $message): void
    {
        $this->output->writeln($message);
    }
}
