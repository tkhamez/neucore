<?php declare(strict_types=1);

namespace Neucore\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait OutputTrait
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

    private function configureOutputTrait(Command $command)
    {
        $command->addOption('log', 'l', InputOption::VALUE_NONE, 'Redirect output to log.');
        $command->addOption('hide-details', null, InputOption::VALUE_NONE, 'Hide detailed output');
    }

    private function executeOutputTrait(InputInterface $input, OutputInterface $output)
    {
        $this->log = (bool) $input->getOption('log');
        $this->hideDetails = (bool) $input->getOption('hide-details');
        $this->output = $output;
    }

    private function writeln($text, $isDetail = true)
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
