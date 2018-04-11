<?php

namespace Tests\Unit\Middleware;

use Brave\Middleware\Cors;
use Psr\Http\Message\ServerRequestInterface;

class CorsTest extends \PHPUnit\Framework\TestCase
{
    public function testAddsHeader()
    {
        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('getHeader')->willReturn(['https://domain.tld']);

        $res = new CorsResponse();
        $next = function ($req, $res) {
            return $res;
        };

        $cors = new Cors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors($req, $res, $next);

        $headers = $response->getHeaders();
        $this->assertSame([
            'Access-Control-Allow-Origin'      => ['https://domain.tld'],
            'Access-Control-Allow-Credentials' => ['true'],
        ], $headers);
    }

    public function testDoesNotAddHeader()
    {
        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('getHeader')->willReturn(['http://domain.tld']);

        $res = new CorsResponse();
        $next = function ($req, $res) {
            return $res;
        };

        $cors = new Cors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors($req, $res, $next);

        $this->assertSame([], $response->getHeaders());
    }
}
