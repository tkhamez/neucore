<?php declare(strict_types=1);

namespace Tests\Unit\Slim\Handlers;

use Neucore\Slim\Handlers\Error;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Slim\CallableResolver;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\RequestFactory;

class ErrorTest extends TestCase
{
    public function testInvoke()
    {
        $logger = new Logger('test');
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        $error = new Error(new CallableResolver(), new ResponseFactory(), $logger);
        $exception = new \ErrorException('msg');

        $error->__invoke(RequestFactory::createRequest(), $exception, true, true, true);

        $this->assertSame('msg', $handler->getRecords()[0]['message']);
        $this->assertSame($exception, $handler->getRecords()[0]['context']['exception']);
    }
}
