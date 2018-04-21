<?php declare(strict_types=1);

namespace Brave\Slim\Session;

/**
 * Wraps $_SESSION variable.
 *
 * Can be instantiated before the session is started as long
 * as it is instantiated again when the session is active
 * (which is done in the NonBlockingSessionMiddleware class).
 */
class SessionData
{
    private static $sess;

    private static $readOnly = true;

    public function __construct()
    {
        if (isset($_SESSION) && self::$sess === null) {
            self::$sess = &$_SESSION;
        }
    }

    public function setReadOnly(bool $readOnly)
    {
        self::$readOnly = $readOnly;
    }

    public function isReadOnly(): bool
    {
        return self::$readOnly;
    }

    /**
     *
     * @return array|null
     */
    public function getAll()
    {
        return self::$sess;
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
        if (self::$sess === null) {
            throw new \RuntimeException('Session not started.');
        }
        return array_key_exists($key, self::$sess) ? self::$sess[$key] : $default;
    }

    /**
     * Set a session variable.
     *
     * @param string $key
     * @param mixed $value
     * @throws \RuntimeException If session is read-only
     * @return $this
     */
    public function set($key, $value)
    {
        if (self::$readOnly) {
            throw new \RuntimeException('Session is read-only.');
        }

        self::$sess[$key] = $value;

        return $this;
    }

    /**
     * Delete a session variable.
     *
     * @param string $key
     * @throws \RuntimeException If session is read-only
     * @return $this
     */
    public function delete($key)
    {
        if (self::$readOnly) {
            throw new \RuntimeException('Session is read-only.');
        }

        if (array_key_exists($key, self::$sess)) {
            unset(self::$sess[$key]);
        }

        return $this;
    }

    /**
     * Clear all session variables, regenerate current session ID
     * and delete the old associated session file.
     *
     * @throws \RuntimeException If session is read-only
     * @return $this
     */
    public function clear()
    {
        if (self::$readOnly) {
            throw new \RuntimeException('Session is read-only.');
        }

        self::$sess = [];

        return $this;
    }
}
