<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Neucore\Util\Http;
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testIpAddress()
    {
        // No REMOTE_ADDR
        $this->assertSame('unknown', Http::ipAddress());

        // Invalid REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] = 'invalid';
        $this->assertSame('unknown', Http::ipAddress());

        // Valid REMOTE_ADDR, no trusted proxies
        $_SERVER['REMOTE_ADDR'] = '150.172.238.178';
        $this->assertSame('150.172.238.178', Http::ipAddress());
        $this->assertSame('150.172.238.178', Http::ipAddress(['1.2.3.4']));

        // Valid REMOTE_ADDR, X-Forwarded-For but no trusted proxy
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '70.191.25.186';
        $this->assertSame('150.172.238.178', Http::ipAddress()); // no trusted proxy
        $this->assertSame('150.172.238.178', Http::ipAddress(['1.2.3.4'])); // untrusted proxy
        $this->assertSame('150.172.238.178', Http::ipAddress(['70.191.25.186'])); // trusted proxy (but REMOTE_ADDR not trusted)

        // REMOTE_ADDR is trusted proxy, single X-Forwarded-For
        $_SERVER['REMOTE_ADDR'] = '150.172.238.178';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '70.191.25.186';
        $this->assertSame('70.191.25.186', Http::ipAddress(['150.172.238.178'])); // trusted proxy

        // Invalid IP in X-Forwarded-For chain - invalid IP is leftmost, so right-to-left scan finds valid IP first
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'invalid, ::1 , 70.191.25.186';
        $this->assertSame('70.191.25.186', Http::ipAddress(['150.172.238.178'])); // trusted proxy

        // Invalid IP encountered during right-to-left scan (rightmost in original = leftmost in reversed)
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '70.191.25.186, invalid';
        $this->assertSame('unknown', Http::ipAddress(['150.172.238.178'])); // trusted proxy

        // Multiple IPs in X-Forwarded-For, first untrusted from right
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 10.0.0.2, 192.168.1.1';
        $this->assertSame('192.168.1.1', Http::ipAddress(['150.172.238.178', '10.0.0.1', '10.0.0.2']));

        // All IPs in chain are trusted
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 10.0.0.2';
        $this->assertSame('unknown', Http::ipAddress(['150.172.238.178', '10.0.0.1', '10.0.0.2']));

        // Empty X-Forwarded-For with trusted proxy
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
        $this->assertSame('unknown', Http::ipAddress(['150.172.238.178']));

        // IPv6 addresses
        $_SERVER['REMOTE_ADDR'] = '::1';
        $this->assertSame('::1', Http::ipAddress());
        $this->assertSame('::1', Http::ipAddress(['1.2.3.4']));

        // IPv6 trusted proxy
        $_SERVER['REMOTE_ADDR'] = '::1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:db8::1';
        $this->assertSame('2001:db8::1', Http::ipAddress(['::1']));

        // IPv4-mapped IPv6 address (::ffff:a.b.c.d) normalization
        $_SERVER['REMOTE_ADDR'] = '::ffff:192.168.1.1';
        $this->assertSame('::ffff:192.168.1.1', Http::ipAddress());
        // Trusted proxy with IPv4-mapped IPv6
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
        $this->assertSame('10.0.0.1', Http::ipAddress(['::ffff:192.168.1.1']));

        // IPv6 with zone ID (should be invalid for filter_var)
        $_SERVER['REMOTE_ADDR'] = 'fe80::1%eth0';
        $this->assertSame('unknown', Http::ipAddress());
    }

    public function testAppId()
    {
        $this->assertNull(Http::appId());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ';
        $this->assertNull(Http::appId());

        $_SERVER['HTTP_AUTHORIZATION'] = '123';
        $this->assertNull(Http::appId());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ';
        $this->assertNull(Http::appId());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode('123');
        $this->assertNull(Http::appId());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode('::');
        $this->assertNull(Http::appId());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode(':');
        $this->assertNull(Http::appId());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode('abc:');
        $this->assertNull(Http::appId());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode('1.23:abc');
        $this->assertNull(Http::appId());

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . base64_encode('123:abc');
        $this->assertSame(123, Http::appId());
    }
}
