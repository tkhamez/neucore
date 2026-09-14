<?php

declare(strict_types=1);

namespace Neucore\Mcp;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server;
use Mcp\Server\Stateless\StatelessProtocol;
use Neucore\Middleware\Psr15\RateLimit;
use Neucore\Service\EsiClient;
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
            ->setDiscovery(__DIR__ . '/Tools')
            ->setContainer($this->container)
            ->setCapabilities(
                (new ServerCapabilities(
                    tools: true,
                    resources: false,
                    prompts: false,
                    logging: false,
                    completions: false,
                ))
                    ->withExtensions($this->getExtensions()),
            )
            ->setLogger($this->getLogger())
            ->buildStateless();
    }

    private function getExtensions(): array
    {
        return [
            'eve.neucore/rateLimits' => [
                'global' => [
                    'headers' => [RateLimit::HEADER_REMAIN, RateLimit::HEADER_RESET],
                    'location' => "Included in the 'headers' field of the tool response.",
                    'description' => 'Neucore API rate limit. ' . RateLimit::HEADER_REMAIN
                        . ' shows remaining requests in the current window. ' . RateLimit::HEADER_RESET
                        . ' is a countdown (in seconds) until the window resets.',
                    'calculation' => 'Remaining requests = ' . RateLimit::HEADER_REMAIN
                        . '. Window reset = wait until ' . RateLimit::HEADER_RESET . ' reaches 0.',
                ],
                'esi' => [
                    'headers' => [
                        EsiClient::HEADER_RATE_LIMIT_GROUP,
                        EsiClient::HEADER_RATE_LIMIT_LIMIT,
                        EsiClient::HEADER_RATE_LIMIT_REMAINING,
                        EsiClient::HEADER_RATE_LIMIT_USED,
                    ],
                    'location' => "Included in the 'headers' field of the tool response.",
                    'description' => 'ESI floating window rate limit. Tokens are consumed per request '
                        . 'based on response status (2xx=2, 3xx=1, 4xx=5, 5xx=0). Tokens are released '
                        . 'back to the bucket after the window size has passed.',
                    'buckets' => 'Each combination of rate limit group + userID has its own bucket. '
                        . 'Authenticated routes use <applicationID>:<characterID> from the Access Token. '
                        . 'Unauthenticated routes use <sourceIP>.',
                    'token_cost' => ['2xx' => 2, '3xx' => 1, '4xx' => 5, '5xx' => 0],
                    'limit_header' => [
                        'format' => 'TOTAL_TOKENS/WINDOW_SIZE',
                        'example' => '150/15m',
                        'note' => 'm=minutes, h=hours. This is the maximum tokens allowed per window, '
                            . 'not a simple counter.',
                    ],
                    'monitoring' => 'Monitor ' . EsiClient::HEADER_RATE_LIMIT_REMAINING
                        . ' for each group. If it approaches 0, slow down.',
                    'on_429' => 'Use the ' . EsiClient::HEADER_RETRY_AFTER
                        . ' header (in seconds) to determine how long to wait before retrying. '
                        . 'Do not calculate from Limit and Remaining.',
                ],
                'esi_legacy' => [
                    'headers' => [EsiClient::HEADER_ERROR_LIMIT_REMAIN, EsiClient::HEADER_ERROR_LIMIT_RESET],
                    'location' => "Included in the 'headers' field of the tool response.",
                    'description' => 'Legacy error rate limit.',
                    'limit' => '100 non-2xx/3xx responses per 60-second window',
                    'status_code' => 420,
                    'calculation' => 'Remaining error quota = ' . EsiClient::HEADER_ERROR_LIMIT_REMAIN
                        . ' . Window reset = wait until ' . EsiClient::HEADER_ERROR_LIMIT_RESET . ' reaches 0.',
                    'advice' => 'Minimize 4xx/5xx responses to stay under this limit. If blocked (HTTP 420), '
                        . 'wait for ' . EsiClient::HEADER_ERROR_LIMIT_RESET . ' to reach 0 before retrying.',
                ],
                'advice' => [
                    'general' => "Don't operate at the limit. Spread requests over time rather than bursting. "
                        . "Avoid */5 cronjobs; use staggered scheduling (e.g. 5 minutes after the last job finished).",
                    'esi_429' => 'Wait for the number of seconds in the ' . EsiClient::HEADER_RETRY_AFTER
                        . ' header before retrying. Some internal EVE server routes may also return 429 '
                        . 'without rate limit headers — this is being deprecated.',
                    'neucore_429' => 'Wait for the number of seconds in ' . RateLimit::HEADER_RESET
                        . ' before retrying.',
                ],
            ],
            'eve.neucore/esiPagination' => [
                "location" => "Pagination headers are included in the 'headers' field of the tool response.",
                "offset_based" => [
                    "description" => "Legacy pagination style used by many standard ESI endpoints.",
                    "query_parameter" => "page=N (1-indexed integer)",
                    "response_headers" => ["X-Pages (total number of pages)"],
                    "page_size" => "Maximum page size varies by endpoint (commonly 5, 10, or 2000). "
                        . "Check the endpoint's OpenAPI spec.",
                    "how_to_use" => "Increment the page parameter starting from 1. "
                        . "Continue until the requested page number equals the value in X-Pages.",
                ],
                "token_based" => [
                    "description" => "Cursor-based pagination for modified-list endpoints (e.g., "
                        . "/v2/corporations/{id}/projects/). Returns objects based on their last modification time.",
                    "response_headers" => ["before (cursor for previous page)", "after (cursor for next page)"],
                    "query_parameters" => [
                        "forward" => "?after=<token>",
                        "backward" => "?before=<token>",
                    ],
                    "end_condition" => "An empty response array [] indicates you have reached the end of the dataset.",
                    "duplicate_behavior" => "You may see the same record multiple times. "
                        . "Results are sorted by last-modified time; if a record is updated,"
                        . " it moves in the sort order and can appear again in subsequent pages.",
                    "best_practice" => "To track changes over time, keep replaying the last 'after' "
                        . "token until the API returns non-empty results. This ensures you capture all updates.",
                ],
            ],
        ];
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
