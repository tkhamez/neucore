<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Neucore\Service\Config;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Protect server against DNS rebinding attacks.
 *
 * When the request carries an `Origin` header it is validated against the
 * allowlist; otherwise the `Host` header is validated. Both checks are
 * case-insensitive and ignore port.
 *
 * Adapted from `Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware`
 * in `mcp/sdk` (Apache License 2.0).
 * @copyright Original © The MCP SDK contributors
 * @license Apache-2.0 (original)
 * @see \Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware
 * @see https://www.apache.org/licenses/LICENSE-2.0
 */
class DnsRebindingProtection implements MiddlewareInterface
{
    /**
     * @var string[]
     */
    private array $allowedHosts = [];

    public function __construct(
        private readonly Config $config,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
        $allowedHosts = $this->config['allowed_hosts'] ?? '';
        if (is_string($allowedHosts)) {
            $this->allowedHosts = array_values(array_filter(
                array_map('strtolower', array_map('trim', explode(',', $allowedHosts))),
            ));
        }
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (count($this->allowedHosts) === 0) {
            return $handler->handle($request);
        }

        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '') {
            if (!$this->isAllowedOrigin($origin)) {
                return $this->createForbiddenResponse('Forbidden: Invalid Origin header.');
            }

            return $handler->handle($request);
        }

        $host = $request->getHeaderLine('Host');
        if ($host !== '' && !$this->isAllowedHost($host)) {
            return $this->createForbiddenResponse('Forbidden: Invalid Host header.');
        }

        return $handler->handle($request);
    }

    private function isAllowedOrigin(string $origin): bool
    {
        $host = parse_url($origin, \PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        return in_array(strtolower($host), $this->allowedHosts, true);
    }

    private function isAllowedHost(string $host): bool
    {
        if (str_starts_with($host, '[')) {
            $closingBracket = strpos($host, ']');
            if (false === $closingBracket) {
                return false;
            }
            $hostname = substr($host, 0, $closingBracket + 1);
        } else {
            $hostname = explode(':', $host, 2)[0];
        }

        return in_array(strtolower($hostname), $this->allowedHosts, true);
    }

    private function createForbiddenResponse(string $message): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse(403)
            ->withHeader('Content-Type', 'text/plain');
        $response->getBody()->write($message);
        return $response;
    }
}
