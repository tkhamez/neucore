<?php

declare(strict_types=1);

namespace Neucore\Log;

class Logger extends \Monolog\Logger
{
    public function addRecord(int $level, string $message, array $context = []): bool
    {
        return parent::addRecord($level, $message, $context);
    }
}
