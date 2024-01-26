<?php

declare(strict_types=1);

namespace Neucore\Log;

use Gelf\Encoder\JsonEncoder;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

class GelfMessageFormatter implements FormatterInterface
{
    private \Monolog\Formatter\GelfMessageFormatter $formatter;

    public function __construct()
    {
        $this->formatter = new \Monolog\Formatter\GelfMessageFormatter();
    }

    public function format(LogRecord $record): string
    {
        $message = $this->formatter->format($record);

        return (new JsonEncoder())->encode($message) . "\n";
    }

    public function formatBatch(array $records)
    {
        return $this->formatter->formatBatch($records);
    }
}
