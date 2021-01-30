<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Middleware\Psr15\HSTS;
use PHPUnit\Framework\TestCase;
use Tests\RequestFactory;
use Tests\RequestHandler;

class HSTSTest extends TestCase
{
    public function testAddsHeader()
    {
        $req = RequestFactory::createRequest();

        $hsts = new HSTS(31536000);
        $response = $hsts->process($req, new RequestHandler());

        $this->assertSame(['Strict-Transport-Security' => ['max-age=31536000']], $response->getHeaders());
    }
}
