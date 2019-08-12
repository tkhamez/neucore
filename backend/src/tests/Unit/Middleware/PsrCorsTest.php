<?php declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Neucore\Middleware\PsrCors;
use Neucore\Psr\ResponseFactory;
use PHPUnit\Framework\TestCase;
use Tests\RequestFactory;

class PsrCorsTest extends TestCase
{
    public function testAddsHeader()
    {
        $req = RequestFactory::createRequest();
        $req = $req->withHeader('HTTP_ORIGIN', 'https://domain.tld');

        $next = function (/** @noinspection PhpUnusedParameterInspection */$req, $res) {
            return $res;
        };

        $cors = new PsrCors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors($req, (new ResponseFactory())->createResponse(), $next);

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

        $next = function (/** @noinspection PhpUnusedParameterInspection */$req, $res) {
            return $res;
        };

        $cors = new PsrCors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors($req, (new ResponseFactory())->createResponse(), $next);

        $this->assertSame([], $response->getHeaders());
    }
}
