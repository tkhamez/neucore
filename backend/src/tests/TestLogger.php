<?php declare(strict_types=1);

namespace Tests;

use Monolog\Handler\TestHandler;
use Monolog\Logger;

class TestLogger extends Logger
{
    public function __construct(string $name, $handlers = array(), $processors = array())
    {
        parent::__construct($name, $handlers, $processors);

        $this->pushHandler(new TestHandler());
    }

    public function getHandler(): TestHandler
    {
        return parent::getHandlers()[0];
    }
}
