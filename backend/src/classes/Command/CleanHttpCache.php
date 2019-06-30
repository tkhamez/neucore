<?php declare(strict_types=1);

namespace Neucore\Command;

use Kevinrob\GuzzleCache\CacheEntry;
use Neucore\Service\Config;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 * see also https://github.com/Kevinrob/guzzle-cache-middleware/issues/106
 */
class CleanHttpCache extends Command
{
    use OutputTrait;

    /**
     * @var Config
     */
    private $config;

    public function __construct(Config $config, LoggerInterface $logger)
    {
        parent::__construct();
        $this->config = $config;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setName('clean-http-cache')
            ->setDescription('Deletes expired entries from the Guzzle cache.');
        $this->configureOutputTrait($this);
    }

    /**
     * @see \Doctrine\Common\Cache\FilesystemCache::doFetch()
     * @see \Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage::fetch()
     * @see \Kevinrob\GuzzleCache\CacheEntry::getTTL()
     * @see \Kevinrob\GuzzleCache\CacheMiddleware::__invoke
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->executeOutputTrait($input, $output);

        /* @var $files \SplFileInfo[] */
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $this->config['guzzle']['cache']['dir']
        ));
        foreach ($files as $fileInfo) {
            if ($fileInfo->isDir()) {
                continue;
            }
            $file = $fileInfo->getRealPath();

            $resource = fopen($file, 'r');
            if (! $resource) {
                continue;
            }

            $lifetime = -1;
            $line = fgets($resource);
            if ($line !== false) {
                $lifetime = (int) $line;
            }

            if ($lifetime !== 0 && $lifetime < time()) {
                fclose($resource);
                unlink($file);
            } else {
                $data  = '';
                while (($line = fgets($resource)) !== false) {
                    $data .= $line;
                }
                fclose($resource);

                $cache = unserialize(unserialize($data));
                if ($cache instanceof CacheEntry && ! $cache->isFresh()) {
                    unlink($file);
                }
            }
        }

        $this->writeln('Guzzle cache cleaned.', false);
    }
}
