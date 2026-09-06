<?php

declare(strict_types=1);

namespace Neucore\Service;

/**
 * Holds the current global rate limit state set by RateLimitApp and RateLimitIP middleware
 * so that MCP tools and other components can report it to clients.
 *
 * Each rate limiter (app, IP) stores its value independently. get() returns the strictest
 * (lowest remaining).
 */
class RateLimitState
{
    private ?int $appRemain = null;

    private ?float $appReset = null;

    private ?int $ipRemain = null;

    private ?float $ipReset = null;

    public function setApp(int $remain, float $reset): void
    {
        $this->appRemain = $remain;
        $this->appReset = $reset;
    }

    public function setIp(int $remain, float $reset): void
    {
        $this->ipRemain = $remain;
        $this->ipReset = $reset;
    }

    /**
     * @return array{remain: ?int, reset: ?float}
     */
    public function get(): array
    {
        $remain = $this->appRemain;
        $reset = $this->appReset;

        if ($this->ipRemain !== null) {
            if ($remain === null || $this->ipRemain < $remain) {
                $remain = $this->ipRemain;
                $reset = $this->ipReset;
            }
        }

        return [
            'remain' => $remain,
            'reset'  => $reset,
        ];
    }
}
