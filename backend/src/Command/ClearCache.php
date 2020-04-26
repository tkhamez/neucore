<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Service\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ClearCache extends Command
{
    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config)
    {
        parent::__construct();

        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('clear-cache')
            ->setDescription('Deletes the PHP-DI, Doctrine and Guzzle caches.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cleared = [];
        $cleared[] = $this->deleteDirectoryContent($this->config['di']['cache_dir']);
        $cleared[] = $this->deleteDirectoryContent($this->config['doctrine']['meta']['proxy_dir']);
        $cleared[] = $this->deleteDirectoryContent($this->config['guzzle']['cache']['dir']);

        $output->writeln('Cleared ' . implode(', ', array_filter($cleared)));

        return 0;
    }

    private function deleteDirectoryContent(string $directory): string
    {
        $dir = realpath($directory);
        if (! $dir) {
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
}
