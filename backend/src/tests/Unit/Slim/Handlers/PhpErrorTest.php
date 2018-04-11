<?php

namespace Tests\Unit\Slim\Handlers;

use Brave\Slim\Handlers\PhpError;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;

class PhpErrorTest extends \PHPUnit\Framework\TestCase
{
    public function testInvoke()
    {
        $logger = new Logger('test');
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        $phpError = new PhpError(true, $logger);
        $exception = new \ErrorException('msg');

        $phpError->__invoke(Request::createFromEnvironment(Environment::mock()), new Response(), $exception);

        $this->assertSame('msg', $handler->getRecords()[0]['message']);
        $this->assertSame($exception, $handler->getRecords()[0]['context']['exception']);
    }
}
