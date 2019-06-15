<?php declare(strict_types=1);

namespace Tests\Unit\Log;

use Gelf\Message;
use Neucore\Log\GelfMessage;
use PHPUnit\Framework\TestCase;

class GelfMessageTest extends TestCase
{
    public function testToString()
    {
        $message = (new Message())->setShortMessage('test');
        $gelfMessage = new GelfMessage($message);

        $this->assertContains('"short_message":"test"', $gelfMessage->__toString());
    }
}
