<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp\Tools;

use Neucore\Mcp\Resources\DocResources;
use Neucore\Mcp\Tools\DocTools;
use PHPUnit\Framework\TestCase;

class DocToolsTest extends TestCase
{
    private DocTools $docTools;
    private DocResources $docResources;

    protected function setUp(): void
    {
        $this->docTools = new DocTools();
        $this->docResources = new DocResources();
    }

    public function testGlobalRateLimitsDelegatesToResource(): void
    {
        $toolData = $this->docTools->globalRateLimits();
        $resourceData = $this->docResources->globalRateLimits();
        self::assertSame($resourceData, $toolData);
    }

    public function testEsiRateLimitsDelegatesToResource(): void
    {
        self::assertSame($this->docResources->esiRateLimits(), $this->docTools->esiRateLimits());
    }

    public function testEsiPaginationDelegatesToResource(): void
    {
        self::assertSame($this->docResources->esiPagination(), $this->docTools->esiPagination());
    }
}
