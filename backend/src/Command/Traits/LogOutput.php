<?php declare(strict_types=1);

namespace Neucore\Command\Traits;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait LogOutput
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var bool
     */
    private $log;

    /**
     * @var bool
     */
    private $hideDetails;

    protected function logOutput(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function configureLogOutput(Command $command): void
    {
        $command->addOption('log', 'l', InputOption::VALUE_NONE, 'Redirect output to log.');
        $command->addOption('hide-details', null, InputOption::VALUE_NONE, 'Hide detailed output');
    }

    protected function executeLogOutput(InputInterface $input, OutputInterface $output): void
    {
        $this->log = (bool) $input->getOption('log');
        $this->hideDetails = (bool) $input->getOption('hide-details');
        $this->output = $output;
    }

    protected function writeLine(string $text, bool $isDetail = true): void
    {
        if ($this->hideDetails && $isDetail) {
            return;
        }
        if ($this->log) {
            $this->logger->info($text);
        } else {
            $this->output->writeln(date('Y-m-d H:i:s ') . $text);
        }
    }
}
