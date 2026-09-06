<?php

declare(strict_types=1);

namespace Neucore\Mcp;

use Mcp\Server;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Schema\Enum\ProtocolVersion;
use Psr\Log\NullLogger;

class McpServer
{
    public function createServer(): Server
    {
        return Server::builder()
            ->setServerInfo('Neucore MCP', '1.0.0', 'MCP server for Neucore EVE Online data')
            ->setProtocolVersion(ProtocolVersion::V2025_11_25)
            ->setDiscovery(__DIR__ . '/Tools')
            ->setSession(new InMemorySessionStore())
            ->setLogger(new NullLogger())
            ->build();
    }
}
