<?php

declare(strict_types=1);

namespace Neucore\Util;

abstract class Http
{
    public static function ipAddress(array $trustedProxies = []): string
    {
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!filter_var($remoteIp, FILTER_VALIDATE_IP)) {
            return 'unknown';
        }

        if (count($trustedProxies) > 0 && !in_array($remoteIp, $trustedProxies, true)) {
            return $remoteIp;
        }

        $forwardedIps = [];
        $forwardedIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (!empty($forwardedIp)) {
            $forwardedIps = array_map('trim', explode(',', $forwardedIp));
        }

        foreach ($forwardedIps as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return $remoteIp;
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
