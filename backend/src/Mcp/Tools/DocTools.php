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
        title: DocResources::TITLE_GLOBAL_RATE_LIMIT,
        description: DocResources::DESC_GLOBAL_RATE_LIMIT,
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
        title: DocResources::TITLE_ESI_RATE_LIMITS,
        description: DocResources::DESC_ESI_RATE_LIMITS,
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
        title: DocResources::TITLE_ESI_PAGINATION,
        description: DocResources::DESC_ESI_PAGINATION,
    )]
    public function esiPagination(): array
    {
        return $this->resources->esiPagination();
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'esi_assets',
        title: DocResources::TITLE_ESI_ASSETS,
        description: DocResources::DESC_ESI_ASSETS,
    )]
    public function esiAssets(): array
    {
        return $this->resources->esiAssets();
    }
}
