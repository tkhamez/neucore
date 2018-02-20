<?php
namespace Tests\Unit\Slim\Handlers;

use Brave\Slim\Handlers\Error;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;

class ErrorTest extends  \PHPUnit\Framework\TestCase
{

    public function testInvoke()
    {
        $logger = new Logger('test');
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        $error = new Error(true, $logger);
        $exception = new \ErrorException('msg');

        $error->__invoke(Request::createFromEnvironment(Environment::mock()), new Response(), $exception);

        $this->assertSame('msg', $handler->getRecords()[0]['message']);
        $this->assertSame($exception, $handler->getRecords()[0]['context']['exception']);
    }
}
