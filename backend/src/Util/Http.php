<?php

declare(strict_types=1);

namespace Neucore\Util;

abstract class Http
{
    private const IP_UNKNOWN = 'unknown';

    /**
     * Retrieve the client IP address considering trusted reverse proxies and X-Forwarded-For header.
     *
     * If REMOTE_ADDR is not a trusted proxy (or no trusted proxies configured), returns REMOTE_ADDR directly.
     * If REMOTE_ADDR is a trusted proxy, parses X-Forwarded-For from right to left and returns
     * the first IP that is NOT in the trusted proxies list (the immediate client IP behind the proxy chain).
     * Returns "unknown" if the client IP cannot be determined reliably.
     *
     * @param string[] $trustedProxies List of trusted proxy IP addresses
     * @return string Valid IPv4/IPv6 address or "unknown"
     */
    public static function ipAddress(array $trustedProxies = []): string
    {
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';

        if (!filter_var($remoteIp, FILTER_VALIDATE_IP)) {
            return self::IP_UNKNOWN;
        }

        if (
            count($trustedProxies) === 0
            || !self::ipIsContainedIn($remoteIp, $trustedProxies)
        ) {
            return $remoteIp;
        }

        // If we are here, REMOTE_ADDR is a trusted proxy.

        $forwardedIp = trim($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwardedIp === '') {
            return self::IP_UNKNOWN;
        }

        $forwardedIps = array_map('trim', explode(',', $forwardedIp));
        foreach (array_reverse($forwardedIps) as $ip) {
            // an invalid IP in the chain makes the client IP unreliable.
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                return self::IP_UNKNOWN;
            }

            // return the first untrusted IP
            if (!self::ipIsContainedIn($ip, $trustedProxies)) {
                return $ip;
            }
        }

        // All IPs in the chain are trusted, return unknown
        return self::IP_UNKNOWN;
    }

    private static function ipIsContainedIn(string $ip, array $ipList): bool
    {
        foreach ($ipList as $ipCheck) {
            $binary1 = self::ipNormalizeBinary($ip);
            $binary2 = self::ipNormalizeBinary($ipCheck);
            if ($binary1 !== false && $binary2 !== false && $binary1 === $binary2) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $ip A human readable IPv4 or IPv6 address.
     * @return string|false the in_addr representation of the given address or false
     */
    private static function ipNormalizeBinary(string $ip): string|false
    {
        $binary = inet_pton($ip);

        if (
            // return on error
            $binary === false
            // return IPv4 addresses without further processing
            || strlen($binary) !== 16
        ) {
            return $binary;
        }

        // IPv4-mapped IPv6 address (RFC 4291): ::ffff:a.b.c.d
        // Binary format: 10 zero bytes + 0xFFFF + 4-byte IPv4
        // Extract the last 4 bytes to make it comparable with plain IPv4
        if (
            substr($binary, 0, 10) === str_repeat("\0", 10)
            && substr($binary, 10, 2) === "\xff\xff"
        ) {
            return substr($binary, 12, 4);
        }

        return $binary;
    }

    public static function appId(): ?int
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($auth)) {
            return null;
        }

        if (!str_contains($auth, 'Bearer ')) {
            return null;
        }

        $token = str_replace('Bearer ', '', $auth);
        if (empty($token)) {
            return null;
        }

        $decoded = base64_decode($token);
        if (!str_contains($decoded, ':') || substr_count($decoded, ':') !== 1) {
            return null;
        }

        $parts = explode(':', $decoded);
        if (!is_numeric($parts[0]) || (string) (int) $parts[0] !== $parts[0]) {
            return null;
        }

        return (int) $parts[0];
    }
}
