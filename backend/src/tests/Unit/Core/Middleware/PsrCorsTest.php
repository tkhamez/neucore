<?php declare(strict_types=1);

namespace Tests\Unit\Core\Middleware;

use Brave\Core\Middleware\PsrCors;
use PHPUnit\Framework\TestCase;

class PsrCorsTest extends TestCase
{
    public function testAddsHeader()
    {
        $req = new PsrCorsTestRequest();
        $req = $req->withHeader('HTTP_ORIGIN', 'https://domain.tld');

        $next = function (/** @noinspection PhpUnusedParameterInspection */$req, $res) {
            return $res;
        };

        $cors = new PsrCors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors($req, new PsrCorsTestResponse(), $next);

        $headers = $response->getHeaders();
        $this->assertSame([
            'Access-Control-Allow-Origin' => ['https://domain.tld'],
            'Access-Control-Allow-Credentials' => ['true'],
        ], $headers);
    }

    public function testDoesNotAddHeader()
    {
        $req = new PsrCorsTestRequest();
        $req = $req->withHeader('HTTP_ORIGIN', 'http://domain.tld');

        $next = function (/** @noinspection PhpUnusedParameterInspection */$req, $res) {
            return $res;
        };

        $cors = new PsrCors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors($req, new PsrCorsTestResponse(), $next);

        $this->assertSame([], $response->getHeaders());
    }
}
