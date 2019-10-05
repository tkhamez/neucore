<?php declare(strict_types=1);

namespace Tests\Unit\Slim;

use Neucore\Slim\ErrorHandler;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Slim\CallableResolver;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\RequestFactory;

class ErrorHandlerTest extends TestCase
{
    public function testInvoke()
    {
        $logger = new Logger('test');
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        $error = new ErrorHandler(new CallableResolver(), new ResponseFactory(), $logger);
        $exception = new \ErrorException('msg');

        $error->__invoke(RequestFactory::createRequest(), $exception, true, true, true);

        $this->assertSame('msg', $handler->getRecords()[0]['message']);
        $this->assertSame($exception, $handler->getRecords()[0]['context']['exception']);
    }
}
