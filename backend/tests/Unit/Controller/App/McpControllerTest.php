<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\App;

use GuzzleHttp\Psr7\Utils;
use Neucore\Controller\App\McpController;
use Neucore\Mcp\McpServer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

class McpControllerTest extends TestCase
{
    #[Test]
    public function constructor_acceptsMcpServer(): void
    {
        $mcpServer = $this->createMock(McpServer::class);

        $controller = new McpController($mcpServer);

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        self::assertInstanceOf(McpController::class, $controller);
    }

    #[Test]
    public function handle_passesRequestToTransport(): void
    {
        $mcpServer = new McpServer(
            $this->createMock(ContainerInterface::class),
            $this->createMock(LoggerInterface::class),
        );

        $controller = new McpController($mcpServer);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/app/v1/mcp')
            ->withHeader('MCP-Protocol-Version', '2026-07-28')
            ->withHeader('Mcp-Method', 'tools/list')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(Utils::streamFor('{"jsonrpc":"2.0","id":1,"method":"tools/list"}'));

        $response = $controller->handle($request);

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        self::assertInstanceOf(ResponseInterface::class, $response);
    }
}
