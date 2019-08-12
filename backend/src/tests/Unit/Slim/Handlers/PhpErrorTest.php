<?php declare(strict_types=1);

namespace Tests\Unit\Slim\Handlers;

use Neucore\Psr\ResponseFactory;
use Neucore\Slim\Handlers\PhpError;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Tests\RequestFactory;

class PhpErrorTest extends TestCase
{
    public function testInvoke()
    {
        $logger = new Logger('test');
        $handler = new TestHandler();
        $logger->pushHandler($handler);

        $phpError = new PhpError(true, $logger);
        $exception = new \ErrorException('msg');

        $phpError->__invoke(
            RequestFactory::createRequest(),
            (new ResponseFactory())->createResponse(),
            $exception
        );

        $this->assertSame('msg', $handler->getRecords()[0]['message']);
        $this->assertSame($exception, $handler->getRecords()[0]['context']['exception']);
    }
}
