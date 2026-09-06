<?php

declare(strict_types=1);

namespace Neucore\Controller;

use Mcp\Server;
use Mcp\Server\Transport\StreamableHttpTransport;
use Neucore\Mcp\McpServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class McpController
{
    private McpServer $mcpServerFactory;

    public function __construct(McpServer $mcpServerFactory)
    {
        $this->mcpServerFactory = $mcpServerFactory;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $server = $this->mcpServerFactory->createServer();
        $transport = new StreamableHttpTransport($request);

        return $transport->listen();
    }
}
