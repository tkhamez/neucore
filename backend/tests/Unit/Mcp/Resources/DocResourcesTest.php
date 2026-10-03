<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp\Resources;

use Neucore\Mcp\Resources\DocResources;
use PHPUnit\Framework\TestCase;

class DocResourcesTest extends TestCase
{
    private DocResources $docResources;

    protected function setUp(): void
    {
        $this->docResources = new DocResources();
    }

    public function testGlobalRateLimits(): void
    {
        $data = $this->docResources->globalRateLimits();

        self::assertArrayHasKey('headers', $data);
        self::assertArrayHasKey('location', $data);
        self::assertArrayHasKey('description', $data);
        self::assertArrayHasKey('calculation', $data);
    }

    public function testEsiRateLimits(): void
    {
        $data = $this->docResources->esiRateLimits();

        self::assertArrayHasKey('new_rate_limits', $data);
        self::assertArrayHasKey('legacy_rate_limits', $data);
        self::assertArrayHasKey('advice', $data);
    }

    public function testEsiPagination(): void
    {
        $data = $this->docResources->esiPagination();

        self::assertArrayHasKey('location', $data);
        self::assertArrayHasKey('offset_based', $data);
        self::assertArrayHasKey('token_based', $data);
    }

    public function testEsiAssets(): void
    {
        $data = $this->docResources->esiAssets();

        self::assertArrayHasKey('endpoint', $data);
        self::assertArrayHasKey('response_schema', $data);
        self::assertArrayHasKey('build_algorithm', $data);
        self::assertArrayHasKey('chain_walk', $data);
        self::assertArrayHasKey('location_flag_decoding', $data);
        self::assertArrayHasKey('asset_safety', $data);
        self::assertArrayHasKey('classification', $data);
        self::assertArrayHasKey('final_locations', $data);
        self::assertArrayHasKey('related_endpoints', $data);
    }
}
