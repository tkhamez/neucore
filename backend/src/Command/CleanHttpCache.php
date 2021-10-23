<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Command\Traits\LogOutput;
use Neucore\Service\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanHttpCache extends Command
{
    use LogOutput;

    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        parent::__construct();
        $this->logOutput($logger);

        $this->config = $config;
    }

    protected function configure(): void
    {
        $this->setName('clean-http-cache')
            ->setDescription('Deletes expired entries from the Guzzle cache.');
        $this->configureLogOutput($this);
    }

    /**
     * @see \Neucore\Factory\HttpClientFactory::getClient()
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->executeLogOutput($input, $output);

        foreach (new \DirectoryIterator($this->config['guzzle']['cache']['dir']) as $fileInfo) {
            /* @var $fileInfo \DirectoryIterator */
            if ($fileInfo->isDir() && !$fileInfo->isDot()) {
                $cache = new FilesystemAdapter('', 0, (string)$fileInfo->getRealPath());
                $cache->prune();
            }
        }

        $this->writeLine('Guzzle cache cleaned.', false);

        return 0;
    }
}
