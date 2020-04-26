<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Middleware\Psr15\Cors;
use PHPUnit\Framework\TestCase;
use Tests\RequestFactory;
use Tests\RequestHandler;

class CorsTest extends TestCase
{
    public function testAddsHeader()
    {
        $req = RequestFactory::createRequest();
        $req = $req->withHeader('HTTP_ORIGIN', 'https://domain.tld');

        $cors = new Cors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors->process($req, new RequestHandler());

        $headers = $response->getHeaders();
        $this->assertSame([
            'Access-Control-Allow-Origin' => ['https://domain.tld'],
            'Access-Control-Allow-Credentials' => ['true'],
        ], $headers);
    }

    public function testDoesNotAddHeader()
    {
        $req = RequestFactory::createRequest();
        $req = $req->withHeader('HTTP_ORIGIN', 'http://domain.tld');

        $cors = new Cors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors->process($req, new RequestHandler());

        $this->assertSame([], $response->getHeaders());
    }
}
