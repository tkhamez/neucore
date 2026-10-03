<?php

declare(strict_types=1);

namespace Neucore\Mcp;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server;
use Mcp\Server\Stateless\StatelessProtocol;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class McpServer
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly RepositoryFactory $repositoryFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function createServer(): StatelessProtocol
    {
        return Server::builder()
            ->setServerInfo(
                $this->getName(),
                NEUCORE_VERSION,
                'MCP server for ' . NEUCORE_NAME . ' EVE Online data',
            )
            ->setModernVersions([ProtocolVersion::V2026_07_28])
            ->setDiscovery(__DIR__, ['Tools', 'Resources'])
            ->setContainer($this->container)
            ->setInstructions(
                'This server provides additional guidance on using its tools as resources. '
                . 'For clients that do not support MCP resources, these are also available as tools. '
                . 'For global rate limits see neucore://doc/global-rate-limits.',
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

    private function getName(): string
    {
        $variable = $this->repositoryFactory
            ->getSystemVariableRepository()
            ->find(SystemVariable::CUSTOMIZATION_DOCUMENT_TITLE);

        if ($variable !== null && $variable->getValue() !== '') {
            return NEUCORE_NAME . ' - ' . $variable->getValue();
        }

        return NEUCORE_NAME;
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
