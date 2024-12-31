<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Exception\RuntimeException;

/**
 * Wraps $_SESSION variable.
 *
 * Can be instantiated before the session is started.
 */
class SessionData
{
    private static bool $readOnly = true;

    public static function setReadOnly(bool $readOnly): void
    {
        self::$readOnly = $readOnly;
    }

    public static function isReadOnly(): bool
    {
        return self::$readOnly;
    }

    public function getAll(): ?array
    {
        return $_SESSION ?? null;
    }

    /**
     * Get a session variable.
     *
     * @throws RuntimeException If session is not started
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! isset($_SESSION)) {
            throw new RuntimeException('Session not started.');
        }
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    /**
     * Set a session variable.
     *
     * @throws RuntimeException If session is read-only or not started
     */
    public function set(string $key, mixed $value): self
    {
        if (self::$readOnly || ! isset($_SESSION)) {
            throw new RuntimeException('Session is read-only or not started.');
        }

        $_SESSION[$key] = $value;

        return $this;
    }

    /**
     * Delete a session variable.
     *
     * @throws RuntimeException If session is read-only
     */
    public function delete(string $key): self
    {
        if (self::$readOnly) {
            throw new RuntimeException('Session is read-only.');
        }

        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
        }

        return $this;
    }

    /**
     * Clear all session variables, remove the cookie
     * and delete the session data from the backend storage.
     *
     * @throws RuntimeException If session is read-only
     */
    public function destroy(): self
    {
        if (self::$readOnly) {
            throw new RuntimeException('Session is read-only.');
        }

        // With "session.use_strict_mode = 1" this also removes the session cookie,
        // see also https://www.php.net/manual/en/function.session-destroy.php.
        $_SESSION = [];

        return $this;
    }
}
