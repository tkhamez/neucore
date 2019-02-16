<?php declare(strict_types=1);

namespace Brave\Slim\Session;

/**
 * Wraps $_SESSION variable.
 *
 * Can be instantiated before the session is started.
 */
class SessionData
{
    private static $readOnly = true;

    public function setReadOnly(bool $readOnly)
    {
        self::$readOnly = $readOnly;
    }

    public function isReadOnly(): bool
    {
        return self::$readOnly;
    }

    /**
     * @return array|null
     */
    public function getAll()
    {
        return isset($_SESSION) ? $_SESSION : null;
    }

    /**
     * Get a session variable.
     *
     * @param string $key
     * @param mixed $default
     * @throws \RuntimeException If session is not started
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (! isset($_SESSION)) {
            throw new \RuntimeException('Session not started.');
        }
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    /**
     * Set a session variable.
     *
     * @param string $key
     * @param mixed $value
     * @throws \RuntimeException If session is read-only or not started
     * @return $this
     */
    public function set(string $key, $value): self
    {
        if (self::$readOnly || ! isset($_SESSION)) {
            throw new \RuntimeException('Session is read-only or not started.');
        }

        $_SESSION[$key] = $value;

        return $this;
    }

    /**
     * Delete a session variable.
     *
     * @throws \RuntimeException If session is read-only
     */
    public function delete(string $key): self
    {
        if (self::$readOnly) {
            throw new \RuntimeException('Session is read-only.');
        }

        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
        }

        return $this;
    }

    /**
     * Clear all session variables, regenerate current session ID
     * and delete the old associated session file.
     *
     * @throws \RuntimeException If session is read-only
     */
    public function clear(): self
    {
        if (self::$readOnly) {
            throw new \RuntimeException('Session is read-only.');
        }

        $_SESSION = [];

        return $this;
    }
}
