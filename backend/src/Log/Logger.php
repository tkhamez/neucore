<?php

declare(strict_types=1);

namespace Neucore\Log;

use Monolog\DateTimeImmutable;
use Monolog\Level;

class Logger extends \Monolog\Logger
{
    /** @noinspection PhpRedundantMethodOverrideInspection */
    public function addRecord(
        int|Level $level,
        string $message,
        array $context = [],
        DateTimeImmutable $datetime = null
    ): bool {
        // If necessary, ignore a message here (return true), e.g. to temporarily ignore deprecated warnings.

        return parent::addRecord($level, $message, $context, $datetime);
    }
}
