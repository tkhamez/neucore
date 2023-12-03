<?php

declare(strict_types=1);

namespace Neucore\Command\Traits;

use Neucore\Service\EsiClient;
use Neucore\Storage\StorageInterface;
use Psr\Log\LoggerInterface;

trait EsiRateLimited
{
    private StorageInterface $storage;

    private LoggerInterface $logger;

    private bool $simulate;

    private ?int $sleepInSeconds = null;

    /**
     * @see EsiController::$errorLimitRemain
     * @see \Neucore\Plugin\Core\EsiClient::$errorLimitRemaining
     */
    private int $errorLimitRemaining = 10;

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
        if (($retryAt = EsiClient::getRateLimitWaitTime($this->storage)) > time()) {
            $sleep = (int) max(1, $retryAt - time());
            $this->logger->info("EsiRateLimited: rate limit hit, sleeping $sleep second(s).");
            $this->sleep($sleep);
        }
    }

    private function checkThrottled(): void
    {
        $now = time();
        if (($retryAt = EsiClient::getThrottledWaitTime($this->storage)) > $now) {
            $sleep = (int) max(1, $retryAt - $now);
            $this->logger->info("EsiRateLimited: hit 'throttled', sleeping $sleep seconds");
            $this->sleep($sleep);
        }
    }

    /**
     * Check ESI error limit and sleeps for max. 60 seconds if it is too low.
     */
    private function checkErrorLimit(): void
    {
        $retryAt = EsiClient::getErrorLimitWaitTime($this->storage, $this->errorLimitRemaining);
        if ($retryAt > 0) {
            $sleep = (int) min(60, $retryAt - time());
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
