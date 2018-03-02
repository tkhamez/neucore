<?php
namespace Brave\Core\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class Sample extends Command
{

    /**
     *
     * @var LoggerInterface
     */
    private $log;

    public function __construct(LoggerInterface $log)
    {
        parent::__construct();

        $this->log = $log;
    }

    protected function configure()
    {
        $this->setName('sample')
            ->setDescription('Sample command')
            ->addArgument('arg', InputArgument::REQUIRED, 'Required argument.')
            ->addOption('opt', 'o', InputArgument::OPTIONAL, 'Optional option.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $arg = $input->getArgument('arg');
        $opt = $input->getOption('opt');

        $this->log->debug('something');

        $output->writeln('Sample command with arg: ' . $arg . ', opt: ' . $opt . ' done.');
    }
}
