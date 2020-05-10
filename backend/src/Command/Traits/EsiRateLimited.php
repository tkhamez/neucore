<?php

declare(strict_types=1);

namespace Neucore\Command\Traits;

use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;

trait EsiRateLimited
{
    /**
     * @var StorageInterface
     */
    private $storage;

    /**
     * @var bool
     */
    private $simulate;

    /**
     * @var null|int
     */
    private $sleepInSeconds;

    protected function esiRateLimited(StorageInterface $storage, bool $simulate = false): void
    {
        $this->storage = $storage;
        $this->simulate = $simulate;
    }

    protected function getSleepInSeconds(): ?int
    {
        return $this->sleepInSeconds;
    }

    /**
     * Check ESI error limit and sleeps for max. 60 seconds if it is too low.
     */
    protected function checkErrorLimit(): void
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
            $sleep = min(60, $data->reset + time() - $data->updated);
            if ($this->simulate) {
                $this->sleepInSeconds =  $sleep;
            } else {
                sleep($sleep);
            }
        }
    }
}
