<?php

declare(strict_types=1);

namespace Neucore\Log;

use Monolog\DateTimeImmutable;

class Logger extends \Monolog\Logger
{
    public function addRecord(
        int $level,
        string $message,
        array $context = [],
        DateTimeImmutable $datetime = null
    ): bool {
        return parent::addRecord($level, $message, $context, $datetime);
    }
}
