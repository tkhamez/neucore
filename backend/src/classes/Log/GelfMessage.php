<?php declare(strict_types=1);

namespace Neucore\Log;

use Gelf\Encoder\JsonEncoder;
use Gelf\Message;

class GelfMessage extends Message
{
    public function __construct(Message $message)
    {
        parent::__construct();

        $this->host = $message->host;
        $this->shortMessage = $message->shortMessage;
        $this->fullMessage = $message->fullMessage;
        $this->timestamp = $message->timestamp;
        $this->level = $message->level;
        $this->facility = $message->facility;
        $this->file = $message->file;
        $this->line = $message->line;
        $this->additionals = $message->additionals;
        $this->version = $message->version;
    }

    public function __toString()
    {
        return (new JsonEncoder())->encode($this) . "\n";
    }
}
