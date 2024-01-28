<?php

declare(strict_types=1);

namespace Tests;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;

class Logger extends \Monolog\Logger
{
    public function __construct($handlers = array(), $processors = array())
    {
        parent::__construct('Test', $handlers, $processors);

        $this->pushHandler(new TestHandler());
    }

    public function getHandler(): ?TestHandler
    {
        $handler = parent::getHandlers()[0];
        return $handler instanceof TestHandler ? $handler : null;
    }

    public function getMessages(): array
    {
        if (($handler = $this->getHandler()) !== null) {
            return array_map(function (LogRecord $item) {
                return $item['message'];
            }, $handler->getRecords());
        }
        return [];
    }
}
