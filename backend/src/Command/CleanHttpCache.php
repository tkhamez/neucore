<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Neucore\Command\Traits\LogOutput;
use Neucore\Factory\HttpClientFactory;
use Neucore\Service\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanHttpCache extends Command
{
    use LogOutput;

    public function __construct(
        private readonly Config $config,
        private readonly Connection $connection,
        LoggerInterface $logger,
    ) {
        parent::__construct();
        $this->logOutput($logger);
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

        $this->writeLine('Started "clean-http-cache"', false);

        if ($this->config['guzzle']['cache']['storage'] === HttpClientFactory::CACHE_STORAGE_DATABASE) {
            $this->clearDatabaseCache();
        } else {
            $this->clearFilesystemCache();
        }

        $this->writeLine('Finished "clean-http-cache"', false);

        return 0;
    }

    private function clearDatabaseCache(): void
    {
        try {
            $ids = $this->connection->fetchAllAssociative('SELECT item_id FROM cache_http');
        } catch (Exception $e) {
            $this->writeLine('Could not get cache entries: ' . $e->getMessage());
            return;
        }

        $namespaces = [];
        foreach ($ids as $id) {
            // e.g. "namespace:key"
            $pos = strpos($id['item_id'], ':');
            if ($pos > 0) {
                $namespaces[] = substr($id['item_id'], 0, $pos);
            }
        }
        $namespaces = array_keys(array_flip($namespaces)); // faster than array_unique for big files

        foreach ($namespaces as $namespace) {
            $adapter = new DoctrineDbalAdapter(
                $this->connection,
                $namespace,
                86400,
                ['db_table' => $this->config['guzzle']['cache']['table']],
            );
            $adapter->prune();
        }
    }

    private function clearFilesystemCache(): void
    {
        foreach (new \DirectoryIterator($this->config['guzzle']['cache']['dir']) as $fileInfo1) {
            /* @var $fileInfo1 \DirectoryIterator */
            if ($fileInfo1->isDir() && !$fileInfo1->isDot()) {
                $dir = (string) $fileInfo1->getRealPath();
                $cache1 = new FilesystemAdapter('', 0, $dir);
                $cache1->prune();

                // clear namespaces of the directory
                foreach (new \DirectoryIterator($dir) as $fileInfo2) {
                    /* @var $fileInfo2 \DirectoryIterator */
                    if ($fileInfo2->isDir() && !$fileInfo2->isDot()) {
                        $name = (string) $fileInfo2->getBasename();
                        if ($name !== '@') {
                            $cache2 = new FilesystemAdapter($name, 0, $dir);
                            $cache2->prune();
                        }
                    }
                }
            }
        }
    }
}
