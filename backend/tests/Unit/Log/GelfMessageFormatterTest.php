<?php declare(strict_types=1);

namespace Tests\Unit\Log;

use Monolog\Logger;
use Neucore\Log\GelfMessageFormatter;
use PHPUnit\Framework\TestCase;

class GelfMessageFormatterTest extends TestCase
{
    public function testFormat()
    {
        $record = array(
            'message' => 'msg',
            'context' => ['exception' => new \Exception('test', 10)],
            'level' => Logger::DEBUG,
            'level_name' => Logger::getLevelName(Logger::DEBUG),
            'channel' => 'channel',
            'datetime' => new \DateTime(),
            'extra' => [],
        );
        $formatter = new GelfMessageFormatter();

        $this->assertStringContainsString('"short_message":"msg"', $formatter->format($record));
    }
}
