<?php

declare(strict_types=1);

namespace Neucore\Command\Traits;

use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
use Psr\Log\LoggerInterface;

trait EsiRateLimited
{
    private StorageInterface $storage;

    private LoggerInterface $logger;

    private bool $simulate;

    private ?int $sleepInSeconds = null;

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
        $this->checkRateLimit();
        $this->checkThrottled();
        $this->checkErrorLimit();
    }

    protected function checkRateLimit(): void
    {
        $timestamp = (int) $this->storage->get(Variables::ESI_RATE_LIMIT);
        if ($timestamp > time()) {
            $sleep = (int) max(1, $timestamp - time());
            $this->logger->info("EsiRateLimited: rate limit hit, sleeping $sleep second(s).");
            $this->sleep($sleep);
        }
    }

    private function checkThrottled(): void
    {
        $timestamp = (int) $this->storage->get(Variables::ESI_THROTTLED);
        if ($timestamp > time()) {
            $sleep = (int) max(1, $timestamp - time());
            $this->logger->info("EsiRateLimited: hit 'throttled', sleeping $sleep seconds");
            $this->sleep($sleep);
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
        if (!$data instanceof \stdClass) {
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
            $this->sleepInSeconds = $seconds;
        } else {
            for ($i = 0; $i < 100; $i++) {
                usleep($seconds * 10000); // seconds * 1,000,000 / 100
            }
        }
    }
}
