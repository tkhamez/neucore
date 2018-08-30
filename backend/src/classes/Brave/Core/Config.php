<?php
namespace Brave\Core;

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
     * @return array|null
     */
    public function get(string $key)
    {
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }
}
