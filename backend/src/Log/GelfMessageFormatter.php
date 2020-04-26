<?php

declare(strict_types=1);

namespace Neucore\Log;

use Gelf\Encoder\JsonEncoder;
use Monolog\Formatter\FormatterInterface;

class GelfMessageFormatter implements FormatterInterface
{
    /**
     * @var \Monolog\Formatter\GelfMessageFormatter
     */
    private $formatter;

    public function __construct()
    {
        $this->formatter = new \Monolog\Formatter\GelfMessageFormatter();
    }

    public function format(array $record): string
    {
        $message = $this->formatter->format($record);

        return (new JsonEncoder())->encode($message) . "\n";
    }

    public function formatBatch(array $records)
    {
        return $this->formatter->formatBatch($records);
    }
}
