<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Middleware\Psr15\BodyParams;
use PHPUnit\Framework\TestCase;
use Tests\RequestFactory;
use Tests\RequestHandler;

class BodyParamsTest extends TestCase
{
    public function testJsonArray()
    {
        $request = RequestFactory::createRequest('POST');
        $request = $request->withHeader('Content-Type', 'application/json;charset=utf8');
        $request->getBody()->write((string) \json_encode([1, (object) ['v' => 2]]));
        $request->getBody()->rewind();

        $handler = new RequestHandler();
        (new BodyParams())->process($request, $handler);

        $this->assertEquals([1, (object) ['v' => 2]], $handler->getRequest()->getParsedBody());
    }

    public function testJsonObject()
    {
        $request = RequestFactory::createRequest('POST');
        $request = $request->withHeader('Content-Type', 'application/json;charset=utf8');
        $request->getBody()->write((string) \json_encode((object) ['v' => [1, 2]]));
        $request->getBody()->rewind();

        $handler = new RequestHandler();
        (new BodyParams())->process($request, $handler);

        $this->assertEquals((object) ['v' => [1, 2]], $handler->getRequest()->getParsedBody());
    }

    public function testFormUrlEncodedPOST()
    {
        $request = RequestFactory::createRequest('POST');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request = $request->withParsedBody(['v1' => 'val1', 'v2' => '2']);

        $handler = new RequestHandler();
        (new BodyParams())->process($request, $handler);

        $this->assertSame(['v1' => 'val1', 'v2' => '2'], $handler->getRequest()->getParsedBody());
    }

    public function testFormUrlEncodedPUT()
    {
        $request = RequestFactory::createRequest('PUT');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $request->getBody()->write(http_build_query(['v1' => 'val1', 'v2' => '2']));
        $request->getBody()->rewind();

        $handler = new RequestHandler();
        (new BodyParams())->process($request, $handler);

        $this->assertSame(['v1' => 'val1', 'v2' => '2'], $handler->getRequest()->getParsedBody());
    }
}
