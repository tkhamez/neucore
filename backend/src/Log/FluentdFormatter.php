<?php

declare(strict_types=1);

namespace Neucore\Log;

use Monolog\LogRecord;

class FluentdFormatter extends \Monolog\Formatter\FluentdFormatter
{
    public function format(LogRecord $record): string
    {
        $newContext = [];
        foreach ($record->context as $key => $value) {
            if ($value instanceof \Throwable) {
                // Note $record->context is readonly.
                $newContext[$key] = [
                    'class' => get_class($value),
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile().':'.$value->getLine(),
                    'trace' => $value->getTrace(),
                ];
            }
        }
        if (count($newContext) > 0) {
            $record = new LogRecord(
                $record->datetime,
                $record->channel,
                $record->level,
                $record->message,
                $newContext,
                $record->extra,
                $record->formatted,
            );
        }

        return parent::format($record) . "\n";
    }
}
