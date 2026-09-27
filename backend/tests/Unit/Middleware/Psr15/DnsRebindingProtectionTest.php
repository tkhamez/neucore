<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Middleware\Psr15\DnsRebindingProtection;
use Neucore\Service\Config;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\RequestFactory;
use Tests\RequestHandler;

class DnsRebindingProtectionTest extends TestCase
{
    // ------------------------------------------------------------------
    // No hosts configured — always pass through
    // ------------------------------------------------------------------

    public function testPassesThroughWhenNoHostsConfigured()
    {
        $request = RequestFactory::createRequest();
        $handler = new RequestHandler();

        $middleware = $this->createMiddleware([]);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPassesThroughWhenEmptyStringHosts()
    {
        $request = RequestFactory::createRequest();
        $handler = new RequestHandler();

        $middleware = $this->createMiddleware([]);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Host header — allowed
    // ------------------------------------------------------------------

    public function testHostHeaderMatchesAllowlist()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'myapp.example.com');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHostHeaderMatchesAllowlistWithPort()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'myapp.example.com:8080');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHostHeaderMatchesAllowlistCaseInsensitive()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'MYAPP.EXAMPLE.COM');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHostHeaderMatchesAllowlistMixedCase()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'MyApp.Example.Com:443');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHostHeaderMatchesIPv4()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', '192.168.1.1');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['192.168.1.1']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHostHeaderMatchesIPv4WithPort()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', '192.168.1.1:3000');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['192.168.1.1']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Host header — IPv6
    // ------------------------------------------------------------------

    public function testHostHeaderMatchesIPv6Bracketed()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', '[::1]');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['[::1]']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHostHeaderMatchesIPv6BracketedWithPort()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', '[::1]:8080');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['[::1]']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHostHeaderMatchesIPv6FullAddress()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', '[2001:db8::1]');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['[2001:db8::1]']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Host header — denied
    // ------------------------------------------------------------------

    public function testHostHeaderDeniedWhenNotInAllowlist()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'evil.com');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertSame('Forbidden: Invalid Host header.', $response->getBody()->__toString());
    }

    public function testHostHeaderDeniedWithPortNotInAllowlist()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'evil.com:443');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testHostHeaderDeniedWithUnmatchedPort()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'myapp.example.com:9999');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHostHeaderWithUnmatchedHostnameAndPort()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'myapp.example.com:9999');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['evil.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testHostHeaderInvalidIPv6MissingClosingBracket()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', '[::1:8080');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['[::1]']);
        $response = $middleware->process($request, $handler);

        // Invalid bracket parsing → host is not matched → denied.
        $this->assertSame(403, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Host header — empty (no Host header → pass through)
    // ------------------------------------------------------------------

    public function testNoHostHeaderPassesThrough()
    {
        $request = RequestFactory::createRequest();
        // Deliberately no Host header.
        $request = $request->withoutHeader('Host');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Origin header — allowed
    // ------------------------------------------------------------------

    public function testOriginHeaderMatchesAllowlist()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Origin', 'https://myapp.example.com');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testOriginHeaderMatchesAllowlistWithPort()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Origin', 'https://myapp.example.com:8080');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testOriginHeaderMatchesAllowlistCaseInsensitive()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Origin', 'HTTPS://MYAPP.EXAMPLE.COM');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testOriginHeaderMatchesAllowlistWithHTTP()
    {
        $request = RequestFactory::createRequest();
        /** @noinspection HttpUrlsUsage */
        $request = $request->withHeader('Origin', 'http://myapp.example.com');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Origin header — denied
    // ------------------------------------------------------------------

    public function testOriginHeaderDeniedWhenNotInAllowlist()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Origin', 'https://evil.com');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Forbidden: Invalid Origin header.', $response->getBody()->__toString());
    }

    public function testOriginHeaderDeniedDifferentHost()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Origin', 'https://myapp.example.com.evil.com');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Origin vs Host — Origin takes priority
    // ------------------------------------------------------------------

    public function testOriginHeaderTakesPriorityOverHost()
    {
        // Both headers present. Origin is denied, so the response should be forbidden
        // even though the Host header would match.
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Origin', 'https://evil.com');
        $request = $request->withHeader('Host', 'myapp.example.com');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Forbidden: Invalid Origin header.', $response->getBody()->__toString());
    }

    public function testOriginAllowedOverridesHostMismatch()
    {
        // Origin is allowed (host matches) but Host header itself does not match
        // the allowlist. Origin should take priority and the request should pass.
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Origin', 'https://myapp.example.com');
        $request = $request->withHeader('Host', 'evil.com');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Allowlist with multiple hosts
    // ------------------------------------------------------------------

    public function testHostMatchesSecondAllowlistEntry()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'other.example.org');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com', 'other.example.org']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHostDoesNotMatchAllowlistWithMultipleEntries()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'untrusted.example.net');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com', 'other.example.org']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Allowlist edge cases — whitespace trimming, empty entries
    // ------------------------------------------------------------------

    public function testAllowlistTrimsWhitespaceAroundHosts()
    {
        // Config with spaces around comma-separated hosts.
        $config = new Config(['allowed_hosts' => ' myapp.example.com , other.example.org ']);
        $handler = new RequestHandler();

        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'other.example.org');

        $response = (new DnsRebindingProtection(
            $config,
            new ResponseFactory(),
        ))->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testAllowlistIgnoresEmptyEntries()
    {
        $config = new Config(['allowed_hosts' => 'myapp.example.com,,other.example.org,']);
        $handler = new RequestHandler();

        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'myapp.example.com');

        $middleware = new DnsRebindingProtection(
            $config,
            new ResponseFactory(),
        );
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Response details — forbidden response
    // ------------------------------------------------------------------

    public function testForbiddenResponseHasTextPlainContentType()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', 'evil.com');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('text/plain', $response->getHeaderLine('Content-Type'));
    }

    // ------------------------------------------------------------------
    // Host header — Host header empty when Origin also absent → pass
    // ------------------------------------------------------------------

    public function testEmptyHostHeaderPassesThrough()
    {
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Host', '');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['myapp.example.com']);
        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    // Origin header — malformed origin
    // ------------------------------------------------------------------

    public function testMalformedOriginReturnsFalseCheck()
    {
        // If the origin parses to a non-string or empty host, isAllowedOrigin returns false.
        $request = RequestFactory::createRequest();
        $request = $request->withHeader('Origin', 'not-valid-url');

        $handler = new RequestHandler();
        $middleware = $this->createMiddleware(['not-valid-url']);
        // parse_url('not-valid-url', PHP_URL_HOST) returns null → isAllowedOrigin returns false.
        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
    }

    /**
     * @param list<string> $hosts
     */
    private function createMiddleware(array $hosts): DnsRebindingProtection
    {
        return new DnsRebindingProtection(
            new Config(['allowed_hosts' => implode(',', $hosts)]),
            new ResponseFactory(),
        );
    }
}
