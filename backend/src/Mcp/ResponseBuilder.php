<?php

declare(strict_types=1);

namespace Neucore\Mcp;

use Neucore\Service\RateLimitState;

/**
 * Builds consistent tool response arrays with global rate limit headers.
 */
class ResponseBuilder
{
    public function __construct(
        private readonly RateLimitState $rateLimitState,
    ) {}

    /**
     * @param array<string, mixed> $additionalHeaders
     * @return array<string, mixed>
     */
    public function success(mixed $body, array $additionalHeaders = []): array
    {
        return [
            'success' => true,
            'statusCode' => 200,
            'body' => $body,
            'headers' => $this->buildHeaders($additionalHeaders),
        ];
    }

    /**
     * @param array<string, mixed> $additionalHeaders
     * @return array<string, mixed>
     */
    public function error(int $statusCode, string $message, array $additionalHeaders = []): array
    {
        return [
            'success' => false,
            'statusCode' => $statusCode,
            'error' => $message,
            'headers' => $this->buildHeaders($additionalHeaders),
        ];
    }

    /**
     * @param array<string, mixed> $additionalHeaders
     * @return array<string, mixed>
     */
    private function buildHeaders(array $additionalHeaders): array
    {
        $headers = $additionalHeaders;

        $globalRateLimit = $this->rateLimitState->get();
        if ($globalRateLimit['remain'] !== null) {
            $headers['X-Neucore-Rate-Limit-Remain'] = $globalRateLimit['remain'];
        }
        if ($globalRateLimit['reset'] !== null) {
            $headers['X-Neucore-Rate-Limit-Reset'] = $globalRateLimit['reset'];
        }

        return $headers;
    }
}
