<?php declare(strict_types=1);

namespace Tests\Unit\Slim\Handlers;

use Neucore\Factory\ResponseFactory;
use Neucore\Slim\Handlers\Error;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Tests\RequestFactory;

class ErrorTest extends TestCase
{
    public function testInvoke()
    {
        $logger = new Logger('test');
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        $error = new Error(true, $logger);
        $exception = new \ErrorException('msg');

        $error->__invoke(
            RequestFactory::createRequest(),
            (new ResponseFactory())->createResponse(),
            $exception
        );

        $this->assertSame('msg', $handler->getRecords()[0]['message']);
        $this->assertSame($exception, $handler->getRecords()[0]['context']['exception']);
    }
}
