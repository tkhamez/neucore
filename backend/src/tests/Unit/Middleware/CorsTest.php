<?php
namespace Tests\Unit\Middleware;

use Brave\Middleware\Cors;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Environment;

class CorsTest extends \PHPUnit\Framework\TestCase
{

    public function testInvoke()
    {
        $origin = 'https://frontend.domain.tld';

        $req = Request::createFromEnvironment(Environment::mock());
        $req = $req->withHeader('HTTP_ORIGIN', $origin);
        $res = new Response();
        $next = function($req, $res) {
            return $res;
        };

        $cors = new Cors([$origin]);
        $response = $cors($req, $res, $next);

        $headers = $response->getHeaders();
        $this->assertSame([
            'Access-Control-Allow-Origin' => [$origin],
            'Access-Control-Allow-Credentials' => ['true'],
        ], $headers);
    }
}
