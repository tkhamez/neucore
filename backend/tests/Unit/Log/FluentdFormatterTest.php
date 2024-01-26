<?php

declare(strict_types=1);

namespace Tests\Unit\Log;

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Neucore\Log\FluentdFormatter;
use PHPUnit\Framework\TestCase;

class FluentdFormatterTest extends TestCase
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
        $formatter = new FluentdFormatter();

        $this->assertStringContainsString(
            '"context":{"exception":{"class":"Exception","message":"test","code":10',
            $formatter->format($record)
        );
    }
}
