<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Neucore\Command\Traits\LogOutput;
use Neucore\Service\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
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

        try {
            $ids = $this->connection->fetchAllAssociative('SELECT item_id FROM cache_http');
        } catch (Exception $e) {
            $this->writeLine('Could not get cache entries: ' . $e->getMessage());
            return 1;
        }

        $namespaces = [];
        foreach ($ids as $id) {
            // e.g. "namespace:key"
            $pos = strpos($id['item_id'], ':');
            if ($pos > 0) {
                $namespaces[] = substr($id['item_id'], 0, $pos);
            }
        }

        foreach ($namespaces as $namespace) {
            $adapter = new DoctrineDbalAdapter(
                $this->connection,
                $namespace,
                86400,
                ['db_table' => $this->config['guzzle']['cache']['table']],
            );
            $adapter->prune();
        }

        $this->writeLine('Guzzle cache cleaned.', false);

        return 0;
    }
}
