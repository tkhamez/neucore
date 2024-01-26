<?php

declare(strict_types=1);

namespace Tests\Unit\Log;

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Neucore\Log\GelfMessageFormatter;
use PHPUnit\Framework\TestCase;

class GelfMessageFormatterTest extends TestCase
{
    public function testFormat()
    {
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'channel',
            Level::Debug,
            'msg',
            ['exception' => new \Exception('test', 10)],
            ['extra'],
            Logger::toMonologLevel(Level::Debug),
        );
        $formatter = new GelfMessageFormatter();

        $this->assertStringContainsString('"short_message":"msg"', $formatter->format($record));
    }
}
