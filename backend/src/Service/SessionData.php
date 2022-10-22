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
     * @param mixed $default
     * @return mixed
     * @throws RuntimeException If session is not started
     */
    public function get(string $key, $default = null)
    {
        if (! isset($_SESSION)) {
            throw new RuntimeException('Session not started.');
        }
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    /**
     * Set a session variable.
     *
     * @param mixed $value
     * @throws RuntimeException If session is read-only or not started
     */
    public function set(string $key, $value): self
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

        $_SESSION = [];

        return $this;
    }
}
