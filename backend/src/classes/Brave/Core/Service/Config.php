<?php
namespace Brave\Core\Service;

/**
 * Wraps the config array to make it injectable.
 */
class Config
{
    /**
     * @var array
     */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return string|array|null
     */
    public function get(string $key, string $key2 = null)
    {
        $value = isset($this->config[$key]) ? $this->config[$key] : null;

        if ($key2 !== null && $value !== null) {
            return isset($value[$key2]) ? $value[$key2] : null;
        }

        return $value;
    }
}
