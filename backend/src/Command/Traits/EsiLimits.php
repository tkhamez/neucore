<?php

declare(strict_types=1);

namespace Neucore\Command\Traits;

use Neucore\Service\EsiClient;
use Neucore\Storage\StorageDatabaseInterface;
use Psr\Log\LoggerInterface;

trait EsiLimits
{
    private StorageDatabaseInterface $storage;

    private LoggerInterface $logger;

    private bool $simulate;

    private ?int $sleepInSeconds = null;

    /**
     * @see EsiController::$errorLimitRemain
     * @see \Neucore\Plugin\Core\EsiClient::$errorLimitRemaining
     */
    private int $errorLimitRemaining = 10;

    /**
     * @see EsiController::$rateLimitRemainPercent
     * @see \Neucore\Plugin\Core\EsiClient::$rateLimitRemainPercent
     */
    private int $rateLimitRemainPercent = 10; # TODO Rate-Limits

    protected function esiLimits(
        StorageDatabaseInterface $storage,
        LoggerInterface $logger,
        bool $simulate = false,
    ): void {
        $this->storage = $storage;
        $this->logger = $logger;
        $this->simulate = $simulate;
    }

    protected function getSleepInSeconds(): ?int
    {
        return $this->sleepInSeconds;
    }

    protected function checkLimits(): void
    {
        $this->checkRateLimited();
        $this->checkRateLimit();
        $this->checkThrottled();
        $this->checkErrorLimit();
    }

    private function checkRateLimited(): void
    {
        if (($retryAt = EsiClient::getRateLimitedWaitTime($this->storage)) > time()) {
            $sleep = (int) max(1, $retryAt - time());
            $this->logger->info("EsiRateLimited: rate limit hit, sleeping $sleep second(s).");
            $this->sleep($sleep);
        }
    }

    /**
     * TODO Rate-Limits: For a proper implementation this needs the endpoint
     *   and character ID for the next request.
     * @see EsiClient::getRateLimitWaitTime()
     */
    private function checkRateLimit(): void {}

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
