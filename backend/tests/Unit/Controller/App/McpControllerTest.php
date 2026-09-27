<?php

declare(strict_types=1);

namespace Tests\Unit\Controller\App;

use GuzzleHttp\Psr7\Utils;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Neucore\Controller\App\McpController;
use Neucore\Mcp\McpServer;
use Neucore\Service\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

class McpControllerTest extends TestCase
{
    #[Test]
    public function constructor_acceptsMcpServerAndConfig(): void
    {
        $mcpServer = $this->createMock(McpServer::class);
        $config    = new Config(['env_var_defaults' => []]);

        $controller = new McpController($mcpServer, $config);

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
        $config    = new Config(['env_var_defaults' => []]);

        $controller = new McpController($mcpServer, $config);

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

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function getConfiguredMiddleware_returnsOnlyCorsWhenHostsEmpty(): void
    {
        $mcpServer = $this->createMock(McpServer::class);
        $config    = new Config([
            'mcp'                => ['allowed_hosts' => ''],
            'env_var_defaults'   => [],
        ]);

        $controller = new McpController($mcpServer, $config);

        $method = new \ReflectionMethod($controller, 'getConfiguredMiddleware');
        $method->setAccessible(true);
        $result = $method->invoke($controller);

        // Empty hosts -> only CorsMiddleware (DnsRebindingProtectionMiddleware removed).
        self::assertCount(1, $result);
        self::assertInstanceOf(CorsMiddleware::class, $result[0],);
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function getConfiguredMiddleware_replacesDnsRebindingWithConfiguredHosts(): void
    {
        $mcpServer = $this->createMock(McpServer::class);
        $config    = new Config([
            'mcp'                => ['allowed_hosts' => 'neucore.domain.tld, localhost'],
            'env_var_defaults'   => [],
        ]);

        $controller = new McpController($mcpServer, $config);

        $method = new \ReflectionMethod($controller, 'getConfiguredMiddleware');
        $method->setAccessible(true);
        $result = $method->invoke($controller);

        // Default middleware replaced: CorsMiddleware + custom DnsRebindingProtectionMiddleware.
        self::assertCount(2, $result);
        self::assertInstanceOf(CorsMiddleware::class, $result[0]);
        self::assertInstanceOf(DnsRebindingProtectionMiddleware::class, $result[1]);

        // Verify the configured hosts are actually set on the middleware.
        $prop = new \ReflectionProperty(DnsRebindingProtectionMiddleware::class, 'allowedHosts');
        self::assertTrue($prop->isReadOnly());
        $prop->setAccessible(true);
        $allowedHosts = $prop->getValue($result[1]);
        self::assertSame(['neucore.domain.tld', 'localhost'], $allowedHosts);
    }
}
