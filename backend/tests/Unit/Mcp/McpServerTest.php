<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use Mcp\Server\Stateless\StatelessProtocol;
use Neucore\Mcp\McpServer;
use Neucore\Middleware\Psr15\RateLimit;
use Neucore\Service\EsiClient;
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
    public function testGetExtensions_containsRateLimitsExtension(): void
    {
        $reflection = new \ReflectionClass($this->mcpServer);
        $method = $reflection->getMethod('getExtensions');
        $extensions = $method->invoke($this->mcpServer);

        self::assertArrayHasKey('eve.neucore/rateLimits', $extensions);
        self::assertArrayHasKey('global', $extensions['eve.neucore/rateLimits']);
        self::assertArrayHasKey('esi', $extensions['eve.neucore/rateLimits']);
        self::assertArrayHasKey('esi_legacy', $extensions['eve.neucore/rateLimits']);
        self::assertArrayHasKey('advice', $extensions['eve.neucore/rateLimits']);
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function testGetExtensions_containsEsiPaginationExtension(): void
    {
        $reflection = new \ReflectionClass($this->mcpServer);
        $method = $reflection->getMethod('getExtensions');
        $extensions = $method->invoke($this->mcpServer);

        self::assertArrayHasKey('eve.neucore/esiPagination', $extensions);
        self::assertArrayHasKey('offset_based', $extensions['eve.neucore/esiPagination']);
        self::assertArrayHasKey('token_based', $extensions['eve.neucore/esiPagination']);
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function testGetExtensions_globalRateLimitHeaders(): void
    {
        $reflection = new \ReflectionClass($this->mcpServer);
        $method = $reflection->getMethod('getExtensions');
        $extensions = $method->invoke($this->mcpServer);

        $global = $extensions['eve.neucore/rateLimits']['global'];
        self::assertSame(
            [RateLimit::HEADER_REMAIN, RateLimit::HEADER_RESET],
            $global['headers'],
        );
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function testGetExtensions_esiRateLimitHeaders(): void
    {
        $reflection = new \ReflectionClass($this->mcpServer);
        $method = $reflection->getMethod('getExtensions');
        $extensions = $method->invoke($this->mcpServer);

        $esi = $extensions['eve.neucore/rateLimits']['esi'];
        self::assertContains(EsiClient::HEADER_RATE_LIMIT_GROUP, $esi['headers']);
        self::assertContains(EsiClient::HEADER_RATE_LIMIT_LIMIT, $esi['headers']);
        self::assertContains(EsiClient::HEADER_RATE_LIMIT_REMAINING, $esi['headers']);
        self::assertContains(EsiClient::HEADER_RATE_LIMIT_USED, $esi['headers']);
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function testGetExtensions_esiLegacyRateLimitHeaders(): void
    {
        $reflection = new \ReflectionClass($this->mcpServer);
        $method = $reflection->getMethod('getExtensions');
        $extensions = $method->invoke($this->mcpServer);

        $esiLegacy = $extensions['eve.neucore/rateLimits']['esi_legacy'];
        self::assertContains(EsiClient::HEADER_ERROR_LIMIT_REMAIN, $esiLegacy['headers']);
        self::assertContains(EsiClient::HEADER_ERROR_LIMIT_RESET, $esiLegacy['headers']);
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function testGetExtensions_esiTokenCost(): void
    {
        $reflection = new \ReflectionClass($this->mcpServer);
        $method = $reflection->getMethod('getExtensions');
        $extensions = $method->invoke($this->mcpServer);

        $esi = $extensions['eve.neucore/rateLimits']['esi'];
        self::assertSame(['2xx' => 2, '3xx' => 1, '4xx' => 5, '5xx' => 0], $esi['token_cost']);
    }

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function testGetExtensions_paginationInfo(): void
    {
        $reflection = new \ReflectionClass($this->mcpServer);
        $method = $reflection->getMethod('getExtensions');
        $extensions = $method->invoke($this->mcpServer);

        $pagination = $extensions['eve.neucore/esiPagination'];
        self::assertArrayHasKey('location', $pagination);
        self::assertArrayHasKey('offset_based', $pagination);
        self::assertArrayHasKey('token_based', $pagination);
        self::assertArrayHasKey('description', $pagination['offset_based']);
        self::assertArrayHasKey('query_parameter', $pagination['offset_based']);
        self::assertArrayHasKey('response_headers', $pagination['offset_based']);
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
