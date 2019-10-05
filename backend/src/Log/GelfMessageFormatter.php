<?php declare(strict_types=1);

namespace Neucore\Log;

use Gelf\Encoder\JsonEncoder;

class GelfMessageFormatter extends \Monolog\Formatter\GelfMessageFormatter
{
    public function format(array $record)
    {
        $message = parent::format($record);

        return (new JsonEncoder())->encode($message) . "\n";
    }
}
