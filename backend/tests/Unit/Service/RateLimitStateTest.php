<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Service\RateLimitState;
use PHPUnit\Framework\TestCase;

class RateLimitStateTest extends TestCase
{
    public function testGet_initial(): void
    {
        $state = new RateLimitState();

        self::assertSame(['remain' => null, 'reset' => null], $state->get());
    }

    public function testSetAppGet(): void
    {
        $state = new RateLimitState();

        $state->setApp(42, 10.5);

        self::assertSame(['remain' => 42, 'reset' => 10.5], $state->get());
    }

    public function testSetIpGet(): void
    {
        $state = new RateLimitState();

        $state->setIp(30, 8.0);

        self::assertSame(['remain' => 30, 'reset' => 8.0], $state->get());
    }

    public function testGet_returnsStrictestLimit(): void
    {
        $state = new RateLimitState();

        $state->setApp(50, 15.0);
        self::assertSame(['remain' => 50, 'reset' => 15.0], $state->get());

        $state->setIp(20, 8.0);
        self::assertSame(['remain' => 20, 'reset' => 8.0], $state->get());
    }

    public function testGet_returnsAppWhenStricter(): void
    {
        $state = new RateLimitState();

        $state->setIp(50, 15.0);
        $state->setApp(10, 5.0);

        self::assertSame(['remain' => 10, 'reset' => 5.0], $state->get());
    }

    public function testGet_ignoresLooserSecondLimiter(): void
    {
        $state = new RateLimitState();

        $state->setApp(20, 10.0);
        $state->setIp(100, 60.0);

        self::assertSame(['remain' => 20, 'reset' => 10.0], $state->get());
    }

    public function testGet_OnlyAppSet(): void
    {
        $state = new RateLimitState();
        $state->setApp(42, 10.5);

        self::assertSame(['remain' => 42, 'reset' => 10.5], $state->get());
    }

    public function testGet_OnlyIpSet(): void
    {
        $state = new RateLimitState();
        $state->setIp(30, 8.0);

        self::assertSame(['remain' => 30, 'reset' => 8.0], $state->get());
    }
}
