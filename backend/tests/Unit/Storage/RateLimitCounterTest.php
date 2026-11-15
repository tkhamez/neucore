<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use Neucore\Storage\RateLimitCounter;
use PHPUnit\Framework\TestCase;

class RateLimitCounterTest extends TestCase
{
    public function testCreate(): void
    {
        $counter = RateLimitCounter::create(10, 12345.678);
        $this->assertSame(9, $counter->remaining);
        $this->assertSame(12345.678, $counter->created);
    }

    public function testNextNull(): void
    {
        $counter = RateLimitCounter::next(null, 10, 60, 12345.0);
        $this->assertSame(9, $counter->remaining);
        $this->assertSame(12345.0, $counter->created);
    }

    public function testNextValid(): void
    {
        $counter = RateLimitCounter::next('{"remaining":5,"created":12300.5}', 10, 60, 12345.0);
        $this->assertSame(4, $counter->remaining);
        $this->assertSame(12300.5, $counter->created);
    }

    public function testNextValid_CreatedAsInt(): void
    {
        $counter = RateLimitCounter::next('{"remaining":5,"created":12300}', 10, 60, 12359.9);
        $this->assertSame(4, $counter->remaining);
        $this->assertSame(12300.0, $counter->created);
    }

    public function testNext_WindowExpired(): void
    {
        $counter = RateLimitCounter::next('{"remaining":5,"created":12300}', 10, 60, 12361.0);
        $this->assertSame(9, $counter->remaining);
        $this->assertSame(12361.0, $counter->created);
    }

    public function testNext_WindowExpired_AtExactlyResetTime(): void
    {
        $counter = RateLimitCounter::next('{"remaining":5,"created":12300}', 10, 60, 12360.0);
        $this->assertSame(9, $counter->remaining);
        $this->assertSame(12360.0, $counter->created);
    }

    public function testNext_InvalidJson(): void
    {
        $counter = RateLimitCounter::next('invalid json', 10, 60, 12345.0);
        $this->assertSame(9, $counter->remaining);
        $this->assertSame(12345.0, $counter->created);
    }

    public function testNext_InvalidRemaining(): void
    {
        $counter = RateLimitCounter::next('{"remaining":"five","created":12300}', 10, 60, 12345.0);
        $this->assertSame(9, $counter->remaining);
        $this->assertSame(12345.0, $counter->created);
    }

    public function testNext_MissingKeys(): void
    {
        $counter = RateLimitCounter::next('{}', 10, 60, 12345.0);
        $this->assertSame(9, $counter->remaining);
        $this->assertSame(12345.0, $counter->created);
    }

    public function testToJson(): void
    {
        $counter = new RateLimitCounter(5, 12345.678);
        $this->assertSame('{"remaining":5,"created":12345.678}', $counter->toJson());
    }
}
