<?php declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Neucore\Middleware\PsrCors;
use PHPUnit\Framework\TestCase;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;

class PsrCorsTest extends TestCase
{
    public function testAddsHeader()
    {
        $req = Request::createFromEnvironment(Environment::mock());
        $req = $req->withHeader('HTTP_ORIGIN', 'https://domain.tld');

        $next = function (/** @noinspection PhpUnusedParameterInspection */$req, $res) {
            return $res;
        };

        $cors = new PsrCors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors($req, new Response(), $next);

        $headers = $response->getHeaders();
        $this->assertSame([
            'Access-Control-Allow-Origin' => ['https://domain.tld'],
            'Access-Control-Allow-Credentials' => ['true'],
        ], $headers);
    }

    public function testDoesNotAddHeader()
    {
        $req = Request::createFromEnvironment(Environment::mock());
        $req = $req->withHeader('HTTP_ORIGIN', 'http://domain.tld');

        $next = function (/** @noinspection PhpUnusedParameterInspection */$req, $res) {
            return $res;
        };

        $cors = new PsrCors(['https://domain.tld', 'https://domain2.tld']);
        $response = $cors($req, new Response(), $next);

        $this->assertSame([], $response->getHeaders());
    }
}
