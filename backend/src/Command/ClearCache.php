<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Neucore\Factory\HttpClientFactory;
use Neucore\Service\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearCache extends Command
{
    private OutputInterface $output;

    public function __construct(
        private readonly Config $config,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('clear-cache')
            ->setDescription('Deletes the PHP-DI, Doctrine and Guzzle caches.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $cleared = [];
        $cleared[] = $this->deleteDirectoryContent($this->config['di']['cache_dir']);
        $cleared[] = $this->deleteDirectoryContent($this->config['doctrine']['meta']['proxy_dir']);
        if ($this->config['guzzle']['cache']['storage'] === HttpClientFactory::CACHE_STORAGE_DATABASE) {
            $cleared[] = $this->deleteDatabaseContent($this->config['guzzle']['cache']['table']);
        } else {
            $cleared[] = $this->deleteDirectoryContent($this->config['guzzle']['cache']['dir']);
        }

        $output->writeln('Cleared ' . implode(', ', array_filter($cleared)));

        return 0;
    }

    private function deleteDirectoryContent(string $directory): string
    {
        $dir = realpath($directory);
        if (!$dir) {
            return '';
        }

        $directory = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) { /* @var $file \SplFileInfo */
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        return $dir;
    }

    private function deleteDatabaseContent(string $tableName): string
    {
        try {
            $this->connection->executeQuery("DELETE FROM $tableName WHERE 1");
        } catch (Exception $e) {
            $this->output->writeln("Failed to clear database table $tableName: " . $e->getMessage());
            return '';
        }

        return "Database table $tableName";
    }
}
