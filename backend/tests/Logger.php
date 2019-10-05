<?php declare(strict_types=1);

namespace Tests;

use Monolog\Handler\TestHandler;

class Logger extends \Monolog\Logger
{
    public function __construct(string $name, $handlers = array(), $processors = array())
    {
        parent::__construct($name, $handlers, $processors);

        $this->pushHandler(new TestHandler());
    }

    public function getHandler(): ?TestHandler
    {
        $handler = parent::getHandlers()[0];
        return $handler instanceof TestHandler ? $handler : null;
    }
}
