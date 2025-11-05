<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use Neucore\Data\EsiRateLimit;
use PHPUnit\Framework\TestCase;

class EsiRateLimitTest extends TestCase
{
    /**
     * @var array<string, EsiRateLimit>
     */
    private array $rateLimits = [];

    private string $jsonFitting = '';

    protected function setUp(): void
    {
        $this->rateLimits = [
            'fitting,123456' => new EsiRateLimit('fitting', '150/15m', 148, 2, 123456),
        ];
        $this->jsonFitting = '"fitting,123456":' .
            '{"group":"fitting","limit":"150/15m","remaining":148,"used":2,"characterId":123456}';
    }

    public function testToJson(): void
    {
        $actual = EsiRateLimit::toJson($this->rateLimits);
        $this->assertSame('{' . $this->jsonFitting . '}', $actual);
    }

    public function testToJson_Invalid(): void
    {
        $rateLimits = $this->rateLimits;
        $rateLimits['fatigue,123456'] = [];
        $rateLimits[''] = new EsiRateLimit('fatigue', '1200/15m', 1198, 2, null);

        // @phpstan-ignore argument.type
        $actual = EsiRateLimit::toJson($rateLimits);

        $this->assertSame('{' . $this->jsonFitting . '}', $actual);
    }

    public function testFromJson(): void
    {
        $actual = EsiRateLimit::fromJson('{' . $this->jsonFitting . '}');

        $this->assertEquals($this->rateLimits, $actual);
    }

    public function testFromJson_PartialInvalid(): void
    {
        $actual = EsiRateLimit::fromJson(
            '{
                ' . $this->jsonFitting . ',
                "fatigue,123456":{"used":2}
            }',
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
