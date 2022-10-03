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
        $this->assertSame('unknown', Http::ipAddress());

        $_SERVER['REMOTE_ADDR'] = '150.172.238.178';
        $this->assertSame('150.172.238.178', Http::ipAddress());

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '192.168.1.2';
        $this->assertSame('192.168.1.2', Http::ipAddress());

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '::1 , 192.168.1.2';
        $this->assertSame('::1', Http::ipAddress());
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
