<?php

declare(strict_types=1);

namespace Neucore\Mcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Neucore\Mcp\Resources\DocResources;

class DocTools
{
    private DocResources $resources;

    public function __construct()
    {
        $this->resources = new DocResources();
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'global_rate_limits',
        title: 'Global Rate Limits',
        description: 'Returns Neucore API rate limit header documentation (X-Neucore-Rate-Limit-Remain, '
            . 'X-Neucore-Rate-Limit-Reset).',
    )]
    public function globalRateLimits(): array
    {
        return $this->resources->globalRateLimits();
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'esi_rate_limits',
        title: 'ESI Rate Limits',
        description: 'Returns ESI rate limit documentation (new floating window + legacy error limits, '
            . 'token costs, advice).',
    )]
    public function esiRateLimits(): array
    {
        return $this->resources->esiRateLimits();
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'esi_pagination',
        title: 'ESI Pagination',
        description: 'Returns ESI pagination documentation (offset-based and cursor-based pagination).',
    )]
    public function esiPagination(): array
    {
        return $this->resources->esiPagination();
    }
}
