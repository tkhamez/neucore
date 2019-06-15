<?php declare(strict_types=1);

namespace Neucore\Log;

class GelfMessageFormatter extends \Monolog\Formatter\GelfMessageFormatter
{
    public function format(array $record)
    {
        return new GelfMessage(parent::format($record));
    }
}
