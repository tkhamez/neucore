<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Middleware\Psr15\OAuthRedirect;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\RequestFactory;
use Tests\RequestHandler;

class OAuthRedirectTest extends TestCase
{
    private OAuthRedirect $middleware;

    protected function setUp(): void
    {
        $this->middleware = new OAuthRedirect(new ResponseFactory());
    }

    public function testPassesThroughOtherPaths()
    {
        $request = RequestFactory::createRequest('GET', '/other-path');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testPassesThroughCallbackWithRedirectedFlag()
    {
        $request = RequestFactory::createRequest('GET', '/login-callback?code=abc&state=xyz&redirected=1');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testPassesThroughLegacyCallbackWithRedirectedFlag()
    {
        $request = RequestFactory::createRequest('GET', '/api/user/auth/callback?code=abc&state=xyz&redirected=1');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testMetaRefreshWithQuery()
    {
        $request = RequestFactory::createRequest('GET', '/login-callback?code=abc&state=xyz');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
        $this->assertStringContainsString(
            '<meta http-equiv="refresh" content="0; url=/login-callback?code=abc&amp;state=xyz&amp;redirected=1">',
            (string) $response->getBody(),
        );
    }

    public function testMetaRefreshWithLegacyPath()
    {
        $request = RequestFactory::createRequest('GET', '/api/user/auth/callback?code=abc&state=xyz');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            '<meta http-equiv="refresh" '
            . 'content="0; url=/api/user/auth/callback?code=abc&amp;state=xyz&amp;redirected=1">',
            (string) $response->getBody(),
        );
    }

    public function testMetaRefreshWithoutQuery()
    {
        $request = RequestFactory::createRequest('GET', '/login-callback');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            '<meta http-equiv="refresh" content="0; url=/login-callback?redirected=1">',
            (string) $response->getBody(),
        );
    }

    public function testMetaRefreshKeepsErrorParameters()
    {
        $request = RequestFactory::createRequest(
            'GET',
            '/login-callback?error=access_denied&error_description=The+user+denied+access.',
        );
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertStringContainsString(
            '<meta http-equiv="refresh" '
            . 'content="0; url=/login-callback?error=access_denied&amp;error_description=The+user+denied+access.'
            . '&amp;redirected=1">',
            (string) $response->getBody(),
        );
    }

    public function testMetaRefreshEscapesPercentEncodedXss()
    {
        $request = RequestFactory::createRequest(
            'GET',
            '/login-callback?state=%22%3E%3Cscript%3Ealert(1)%3C%2Fscript%3E',
        );
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $body = (string) $response->getBody();
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertMatchesRegularExpression(
            '#<meta http-equiv="refresh" content="0; url=[^"]*">#',
            $body,
        );
    }

    public function testMetaRefreshEscapesRawDoubleQuote()
    {
        $request = RequestFactory::createRequest('GET', '/login-callback?state="&code=x');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $body = (string) $response->getBody();
        // The double quote must not appear unescaped inside the HTML attribute.
        $this->assertStringNotContainsString('state="', $body);
        $this->assertMatchesRegularExpression(
            '#<meta http-equiv="refresh" content="0; url=[^"]*">#',
            $body,
        );
    }
}
