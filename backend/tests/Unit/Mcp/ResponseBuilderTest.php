<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp;

use Neucore\Mcp\ResponseBuilder;
use Neucore\Service\RateLimitState;
use PHPUnit\Framework\TestCase;

class ResponseBuilderTest extends TestCase
{
    private ResponseBuilder $builder;

    private RateLimitState $rateLimitState;

    protected function setUp(): void
    {
        $this->rateLimitState = new RateLimitState();
        $this->builder = new ResponseBuilder($this->rateLimitState);
    }

    public function testSuccess_returnsCorrectStructure(): void
    {
        $result = $this->builder->success(['key' => 'value']);

        self::assertSame([
            'success' => true,
            'statusCode' => 200,
            'body' => ['key' => 'value'],
            'headers' => [],
        ], $result);
    }

    public function testSuccess_includesGlobalRateLimit(): void
    {
        $this->rateLimitState->setApp(42, 10.5);

        $result = $this->builder->success(['data']);

        self::assertSame(42, $result['headers']['X-Neucore-Rate-Limit-Remain']);
        self::assertSame(10.5, $result['headers']['X-Neucore-Rate-Limit-Reset']);
    }

    public function testSuccess_includesAdditionalHeaders(): void
    {
        $result = $this->builder->success('body', ['X-Custom' => 'test']);

        self::assertSame('test', $result['headers']['X-Custom']);
        self::assertSame('body', $result['body']);
    }

    public function testSuccess_mergesRateLimitWithAdditionalHeaders(): void
    {
        $this->rateLimitState->setIp(20, 5.0);

        $result = $this->builder->success('data', ['X-Custom' => 'val']);

        self::assertSame(20, $result['headers']['X-Neucore-Rate-Limit-Remain']);
        self::assertSame(5.0, $result['headers']['X-Neucore-Rate-Limit-Reset']);
        self::assertSame('val', $result['headers']['X-Custom']);
    }

    public function testSuccess_NoRateLimitReturnsEmptyHeaders(): void
    {
        $result = $this->builder->success('data');

        self::assertArrayNotHasKey('X-Neucore-Rate-Limit-Remain', $result['headers']);
        self::assertArrayNotHasKey('X-Neucore-Rate-Limit-Reset', $result['headers']);
    }

    public function testSuccess_withNullBody(): void
    {
        $result = $this->builder->success(null);

        self::assertNull($result['body']);
        self::assertTrue($result['success']);
        self::assertSame(200, $result['statusCode']);
    }

    public function testSuccess_withArrayBody(): void
    {
        $body = [['id' => 1], ['id' => 2]];
        $result = $this->builder->success($body);

        self::assertSame($body, $result['body']);
    }

    public function testError_returnsCorrectStructure(): void
    {
        $result = $this->builder->error(404, 'Not found');

        self::assertSame([
            'success' => false,
            'statusCode' => 404,
            'error' => 'Not found',
            'headers' => [],
        ], $result);
    }

    public function testError_includesGlobalRateLimit(): void
    {
        $this->rateLimitState->setApp(0, 30.0);

        $result = $this->builder->error(429, 'Rate limited');

        self::assertSame(false, $result['success']);
        self::assertSame(0, $result['headers']['X-Neucore-Rate-Limit-Remain']);
        self::assertSame(30.0, $result['headers']['X-Neucore-Rate-Limit-Reset']);
    }

    public function testError_withAdditionalHeaders(): void
    {
        $result = $this->builder->error(500, 'Server error', ['X-Retry-After' => '60']);

        self::assertSame(500, $result['statusCode']);
        self::assertSame('Server error', $result['error']);
        self::assertSame('60', $result['headers']['X-Retry-After']);
    }
}
