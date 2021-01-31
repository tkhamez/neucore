<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Middleware\Psr15\CSRFToken;
use Neucore\Service\SessionData;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\Helper;
use Tests\RequestHandler;
use Tests\Unit\TestCase;

class CSRFTokenTest extends TestCase
{
    protected function setUp(): void
    {
        (new Helper())->resetSessionData();
    }

    public function testProcess()
    {
        $request = $this->createRequestWithRoute('POST', '/include-route/something');
        $_SESSION = [];
        SessionData::setReadOnly(false);
        $sessionData = new SessionData();
        $middleware = new CSRFToken(new ResponseFactory(), $sessionData, '/include-route');

        $response = $middleware->process($request, new RequestHandler());
        $this->assertSame(403, $response->getStatusCode());

        $request = $request->withHeader(CSRFToken::CSRF_HEADER_NAME, 'token');
        $sessionData->set(CSRFToken::CSRF_SESSION_NAME, 'token');
        $response = $middleware->process($request, new RequestHandler());
        $this->assertSame(200, $response->getStatusCode());
    }
}
