<?php

declare(strict_types=1);

namespace Neucore\Command\Traits;

use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Psr\Log\LoggerInterface;

trait EsiRateLimited
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $simulate;

    /**
     * @var null|int
     */
    private $sleepInSeconds;

    protected function esiRateLimited(StorageInterface $storage, LoggerInterface $logger, bool $simulate = false): void
    {
        $this->storage = $storage;
        $this->logger = $logger;
        $this->simulate = $simulate;
    }

    protected function getSleepInSeconds(): ?int
    {
        return $this->sleepInSeconds;
    }

    protected function checkForErrors(): void
    {
        $this->checkThrottled();
        $this->checkErrorLimit();
    }

    private function checkThrottled(): void
    {
        if ($this->storage->get(Variables::ESI_THROTTLED) === '1') {
            $this->logger->info('EsiRateLimited: hit "throttled", sleeping 60 seconds');
            $this->sleep(60);
        }
    }

    /**
     * Check ESI error limit and sleeps for max. 60 seconds if it is too low.
     */
    private function checkErrorLimit(): void
    {
        $var = $this->storage->get(Variables::ESI_ERROR_LIMIT);
        if ($var === null) {
            return;
        }

        $data = \json_decode($var);
        if (! $data instanceof \stdClass) {
            return;
        }

        if ((int) $data->updated + $data->reset < time()) {
            return;
        }

        if ($data->remain < 10) {
            $sleep = (int) min(60, $data->reset + time() - $data->updated);
            $this->logger->info("EsiRateLimited: hit error limit, sleeping $sleep seconds");
            $this->sleep($sleep);
        }
    }

    private function sleep(int $seconds): void
    {
        if ($this->simulate) {
            $this->sleepInSeconds =  $seconds;
        } else {
            for ($i = 0; $i < 100; $i++) {
                usleep($seconds * 1000 * 10); // seconds * 1000000 / 100
            }
        }
    }
}
