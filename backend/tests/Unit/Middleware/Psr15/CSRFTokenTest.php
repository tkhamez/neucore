<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Middleware\Psr15\CSRFToken;
use Neucore\Service\SessionData;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\Helper;
use Tests\RequestFactory;
use Tests\RequestHandler;

class CSRFTokenTest extends TestCase
{
    protected function setUp(): void
    {
        (new Helper())->resetSessionData();
    }

    public function testProcess()
    {
        $request = RequestFactory::createRequest('POST');
        $_SESSION = [];
        SessionData::setReadOnly(false);
        $sessionData = new SessionData();
        $middleware = new CSRFToken(new ResponseFactory(), $sessionData);

        $response = $middleware->process($request, new RequestHandler());
        $this->assertSame(403, $response->getStatusCode());

        $request = $request->withHeader(CSRFToken::CSRF_HEADER_NAME, 'token');
        $sessionData->set(CSRFToken::CSRF_SESSION_NAME, 'token');
        $response = $middleware->process($request, new RequestHandler());
        $this->assertSame(200, $response->getStatusCode());
    }
}
