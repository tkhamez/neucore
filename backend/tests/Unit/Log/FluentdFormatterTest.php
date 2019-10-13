<?php declare(strict_types=1);

namespace Tests\Unit\Log;

use Monolog\Logger;
use Neucore\Log\FluentdFormatter;
use PHPUnit\Framework\TestCase;

class FluentdFormatterTest extends TestCase
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
        $formatter = new FluentdFormatter();

        $this->assertStringContainsString(
            '"context":{"exception":{"class":"Exception","message":"test","code":10',
            $formatter->format($record)
        );
    }
}
