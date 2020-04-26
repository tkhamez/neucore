<?php

declare(strict_types=1);

namespace Neucore\Log;

class FluentdFormatter extends \Monolog\Formatter\FluentdFormatter
{
    public function format(array $record): string
    {
        foreach ($record['context'] as $key => $value) {
            if ($value instanceof \Throwable) {
                $record['context'][$key] = [
                    'class' => get_class($value),
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile().':'.$value->getLine(),
                    'trace' => $value->getTrace(),
                ];
            }
        }

        return parent::format($record) . "\n";
    }
}
