<?php
namespace Brave\Core;

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
     * @return array
     */
    public function get($key)
    {
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }
}
