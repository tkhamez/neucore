<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use Mcp\Server\Stateless\StatelessProtocol;
use Neucore\Mcp\McpServer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class McpServerTest extends TestCase
{
    private McpServer $mcpServer;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->mcpServer = new McpServer(
            $container,
            $this->logger,
        );
    }

    #[Test]
    public function testCreateServer_returnsStatelessProtocol(): void
    {
        $server = $this->mcpServer->createServer();

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        self::assertInstanceOf(StatelessProtocol::class, $server);
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function testGetLogger_filtersLogLevels(): void
    {
        $reflection = new \ReflectionClass($this->mcpServer);
        $method = $reflection->getMethod('getLogger');
        $filteredLogger = $method->invoke($this->mcpServer);

        self::assertInstanceOf(LoggerInterface::class, $filteredLogger);

        $this->logger
            ->expects(self::once())
            ->method('emergency')
            ->with('emergency', []);

        $this->logger
            ->expects(self::once())
            ->method('alert')
            ->with('alert', []);

        $this->logger
            ->expects(self::once())
            ->method('critical')
            ->with('critical', []);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with('error', []);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with('warning', []);

        $filteredLogger->emergency('emergency', []);
        $filteredLogger->alert('alert', []);
        $filteredLogger->critical('critical', []);
        $filteredLogger->error('error', []);
        $filteredLogger->warning('warning', []);
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function testGetLogger_filtersInfoAndDebug(): void
    {
        $reflection = new \ReflectionClass($this->mcpServer);
        $method = $reflection->getMethod('getLogger');
        $filteredLogger = $method->invoke($this->mcpServer);

        $this->logger
            ->expects(self::never())
            ->method('log');

        $this->logger
            ->expects(self::never())
            ->method('info');

        $this->logger
            ->expects(self::never())
            ->method('notice');

        $this->logger
            ->expects(self::never())
            ->method('debug');

        $filteredLogger->info('should be filtered', []);
        $filteredLogger->notice('should be filtered', []);
        $filteredLogger->debug('should be filtered', []);
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function testGetLogger_passesThroughLogLevels(): void
    {
        $reflection = new \ReflectionClass($this->mcpServer);
        $method = $reflection->getMethod('getLogger');
        $filteredLogger = $method->invoke($this->mcpServer);

        $this->logger
            ->expects(self::once())
            ->method('log')
            ->with(LogLevel::WARNING, 'test warning', ['context' => 'data']);

        $filteredLogger->log(LogLevel::WARNING, 'test warning', ['context' => 'data']);
    }
}
