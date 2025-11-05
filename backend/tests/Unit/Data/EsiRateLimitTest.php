<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use Neucore\Data\EsiRateLimit;
use PHPUnit\Framework\TestCase;

class EsiRateLimitTest extends TestCase
{
    private array $rateLimits = [];

    private string $json = '';

    protected function setUp(): void
    {
        $this->rateLimits = [
            'fitting' => new EsiRateLimit('150/15m', 148, 2),
        ];
        $this->json = '{"fitting":{"limit":"150/15m","remaining":148,"used":2}}';
    }

    public function testToJson(): void
    {
        $actual = EsiRateLimit::toJson($this->rateLimits);
        $this->assertSame($this->json, $actual);
    }

    public function testToJson_Invalid(): void
    {
        $this->rateLimits['fatigue'] = [];
        $this->rateLimits[''] = new EsiRateLimit('1200/15m', 1198, 2);

        $actual = EsiRateLimit::toJson($this->rateLimits);

        $this->assertSame($this->json, $actual);
    }

    public function testFromJson(): void
    {
        $actual = EsiRateLimit::fromJson($this->json);

        $this->assertEquals($this->rateLimits, $actual);
    }

    public function testFromJson_PartialInvalid(): void
    {
        $actual = EsiRateLimit::fromJson(
            '{
                "fitting":{"limit":"150/15m","remaining":148,"used":2},
                "fatigue":{"used":2}
            }'
        );
        $this->assertEquals($this->rateLimits, $actual);
    }

    public function testFromJson_Invalid(): void
    {
        $actual1 = EsiRateLimit::fromJson('invalid');
        $this->assertEquals([], $actual1);

        $actual2 = EsiRateLimit::fromJson('{"":{}}');
        $this->assertEquals([], $actual2);

        $actual3 = EsiRateLimit::fromJson('{"fitting":{}}');
        $this->assertEquals([], $actual3);

        $actual4 = EsiRateLimit::fromJson('{"fitting":{"limit":"150/15m","remaining":148}}');
        $this->assertEquals([], $actual4);
    }
}
