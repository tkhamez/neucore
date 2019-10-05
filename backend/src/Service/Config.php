<?php
namespace Neucore\Service;

/**
 * Wraps the config array to make it injectable.
 */
class Config implements \ArrayAccess
{
    /**
     * @var array
     */
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->config);
    }

    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->replaceEnvVars($this->config[$offset]) : null;
    }

    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException('Read only.');
    }

    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('Read only.');
    }

    /**
     * @param array|string $value
     * @return array|string
     */
    private function replaceEnvVars($value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $val) {
                $value[$k] = $this->replaceEnvVars($val);
            }
            return $value;
        }

        if (preg_match('/\${([A-Z0-9_]+)}/', $value, $matches)) {
            $value = str_replace('${' . $matches[1] . '}', $this->getEnv($matches[1]), $value);
        }

        return $value;
    }

    private function getEnv(string $name): string
    {
        $value = (string) getenv($name);

        if ($value === '' && isset($this->config['env_var_defaults'][$name])) {
            $value = $this->config['env_var_defaults'][$name];
        }

        return (string) $value;
    }
}
