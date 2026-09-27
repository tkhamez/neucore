<?php

declare(strict_types=1);

namespace Neucore\Controller\App;

use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StatelessHttpTransport;
use Neucore\Mcp\McpServer;
use Neucore\Service\Config;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

#[OA\Tag(name: 'Application - MCP')]
class McpController
{
    public function __construct(
        private readonly McpServer $mcpServerFactory,
        private readonly Config $config,
    ) {}

    #[OA\Post(
        path: '/app/v1/mcp',
        operationId: 'mcpV1',
        description: 'Needs role: app-mcp.',
        summary: 'The Neucore MCP server.',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: 'JSON encoded MCP request body.',
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(),
            ),
        ),
        tags: ['Application - MCP'],
        parameters: [
            new OA\Parameter(
                name: 'MCP-Protocol-Version',
                description: 'The MCP protocol version for this request (e.g., "2026-07-28").',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'Mcp-Method',
                description: 'Must mirror the JSON-RPC method in the request body '
                    . '(e.g., "tools/list", "tools/call", "server/discover").',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'Mcp-Name',
                description: 'Required for tools/call and prompts/get: mirrors the tool or prompt name from the body.',
                in: 'header',
                required: false,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'MCP response.',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '204',
                description: 'Accepted. No outgoing messages at this time.',
            ),
            new OA\Response(response: '400', description: 'Bad Request (e.g., unsupported protocol version).'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(
                response: '429',
                description: 'Too Many Requests.',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '500',
                description: 'Server error.',
                content: new OA\JsonContent(type: 'string'),
            ),
        ],
    )]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $protocol = $this->mcpServerFactory->createServer();
        $middleware = $this->getConfiguredMiddleware();
        $transport = new StatelessHttpTransport($protocol, middleware: $middleware);

        return $transport->handle($request);
    }

    /**
     * Return the middleware list for the transport.
     *
     * Starts with the SDK's default middleware (Cors + DnsRebindingProtection),
     * then either removes the DNS rebinding check (empty hosts) or replaces it
     * with a configured allowlist.
     *
     * @return list<MiddlewareInterface>
     */
    private function getConfiguredMiddleware(): array
    {
        $middleware = StatelessHttpTransport::defaultMiddleware();

        $value = (string) ($this->config['mcp']['allowed_hosts'] ?? '');
        if ($value === '') {
            // Empty = allow all hosts (omit DNS rebinding middleware entirely).
            $middleware = array_values(array_filter(
                $middleware,
                fn($m) => !($m instanceof DnsRebindingProtectionMiddleware),
            ));
        } else {
            // Replace the default DNS rebinding middleware with one using configured hosts.
            $hosts = array_map('trim', explode(',', $value));
            $hosts = array_values(array_filter($hosts));
            foreach ($middleware as $i => $m) {
                if ($m instanceof DnsRebindingProtectionMiddleware) {
                    $middleware[$i] = new DnsRebindingProtectionMiddleware($hosts);
                    break;
                }
            }
        }

        return $middleware;
    }
}
