<?php

declare(strict_types=1);

namespace Neucore\Mcp;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server;
use Mcp\Server\Stateless\StatelessProtocol;
use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;

class McpServer
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly LoggerInterface $logger,
    ) {}

    public function createServer(): StatelessProtocol
    {
        return Server::builder()
            ->setServerInfo(
                NEUCORE_NAME,
                NEUCORE_VERSION,
                'MCP server for ' . NEUCORE_NAME . ' EVE Online data',
            )
            ->setModernVersions([ProtocolVersion::V2026_07_28])
            ->setDiscovery(__DIR__, ['Tools', 'Resources'])
            ->setContainer($this->container)
            ->setInstructions(
                'Information on global rate limits is available as an MCP resource at '
                . 'neucore://doc/global-rate-limits. '
                . 'Alternatively, for clients that do not support MCP resources, the information '
                . 'is available as a tool: global_rate_limits.',
            )
            ->setCapabilities(
                (new ServerCapabilities(
                    tools: true,
                    resources: true,
                    prompts: false,
                    logging: false,
                    completions: false,
                )),
            )
            ->setLogger($this->getLogger())
            ->buildStateless();
    }

    private function getLogger(): LoggerInterface
    {
        return // The standard logger logs everything, but there are many debug messages from
            // the library; however, we only want to include warnings and above in our log.
            new class ($this->logger) implements LoggerInterface {
                private readonly LoggerInterface $inner;

                public function __construct(LoggerInterface $inner)
                {
                    $this->inner = $inner;
                }

                public function emergency(\Stringable|string $message, array $context = []): void
                {
                    $this->inner->emergency($message, $context);
                }

                public function alert(\Stringable|string $message, array $context = []): void
                {
                    $this->inner->alert($message, $context);
                }

                public function critical(\Stringable|string $message, array $context = []): void
                {
                    $this->inner->critical($message, $context);
                }

                public function error(\Stringable|string $message, array $context = []): void
                {
                    $this->inner->error($message, $context);
                }

                public function warning(\Stringable|string $message, array $context = []): void
                {
                    $this->inner->warning($message, $context);
                }

                public function notice(\Stringable|string $message, array $context = []): void {}

                public function info(\Stringable|string $message, array $context = []): void {}

                public function debug(\Stringable|string $message, array $context = []): void {}

                public function log($level, \Stringable|string $message, array $context = []): void
                {
                    $levels = [
                        LogLevel::WARNING,
                        LogLevel::ERROR,
                        LogLevel::CRITICAL,
                        LogLevel::ALERT,
                        LogLevel::EMERGENCY,
                    ];
                    if (in_array($level, $levels, true)) {
                        $this->inner->log($level, $message, $context);
                    }
                }
            };
    }
}
