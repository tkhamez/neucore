<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Middleware\Psr15\Cors;
use Neucore\Middleware\Psr15\CSRFToken;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\RequestFactory;
use Tests\RequestHandler;

class CorsTest extends TestCase
{
    public function testAddsHeader()
    {
        $req = RequestFactory::createRequest();
        $req = $req->withHeader('HTTP_ORIGIN', 'https://domain1.tld');

        $cors = new Cors(new ResponseFactory(), ['https://domain1.tld', 'https://domain2.tld']);
        $response = $cors->process($req, new RequestHandler());

        $headers = $response->getHeaders();
        $this->assertSame([
            'Access-Control-Allow-Origin' => ['https://domain1.tld'],
            'Access-Control-Allow-Headers' => [CSRFToken::CSRF_HEADER_NAME, 'Content-Type', 'User-Agent'],
            'Access-Control-Allow-Credentials' => ['true'],
            'Access-Control-Allow-Methods' => ['GET, POST, PUT, DELETE, OPTIONS'],
        ], $headers);
    }

    public function testDoesNotAddHeader()
    {
        $req = RequestFactory::createRequest();
        $req = $req->withHeader('HTTP_ORIGIN', 'https://domain3.tld');

        $cors = new Cors(new ResponseFactory(), ['https://domain1.tld', 'https://domain2.tld']);
        $response = $cors->process($req, new RequestHandler());

        $this->assertSame([], $response->getHeaders());
    }
}
