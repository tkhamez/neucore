<?php declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Brave\Middleware\Cors;
use Psr\Http\Message\ServerRequestInterface;

class CorsTest extends \PHPUnit\Framework\TestCase
{
    public function testAddsHeader()
    {
        /* @var $req \PHPUnit\Framework\MockObject\MockObject|ServerRequestInterface */
        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('getHeader')->willReturn(['https://domain.tld']);

        $next = function (/** @noinspection PhpUnusedParameterInspection */$req, $res) {
            return $res;
        };

        $cors = new Cors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors($req, new TestResponse(), $next);

        $headers = $response->getHeaders();
        $this->assertSame([
            'Access-Control-Allow-Origin' => ['https://domain.tld'],
            'Access-Control-Allow-Credentials' => ['true'],
        ], $headers);
    }

    public function testDoesNotAddHeader()
    {
        /* @var $req \PHPUnit\Framework\MockObject\MockObject|ServerRequestInterface */
        $req = $this->createMock(ServerRequestInterface::class);
        $req->method('getHeader')->willReturn(['http://domain.tld']);

        $next = function (/** @noinspection PhpUnusedParameterInspection */$req, $res) {
            return $res;
        };

        $cors = new Cors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors($req, new TestResponse(), $next);

        $this->assertSame([], $response->getHeaders());
    }
}
