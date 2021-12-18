<?php

declare(strict_types=1);

namespace Neucore\Service;

/**
 * Wraps the config array to make it injectable.
 */
class Config implements \ArrayAccess
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->config);
    }

    /**
     * @param mixed $offset
     * @return array|string|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->replaceEnvVars($this->config[$offset]) : null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws \BadMethodCallException
     */
    public function offsetSet($offset, $value): void
    {
        throw new \BadMethodCallException('Read only.');
    }

    /**
     * @param mixed $offset
     * @throws \BadMethodCallException
     */
    public function offsetUnset($offset): void
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

        if (preg_match('/\${([A-Z0-9_]+)}/', (string) $value, $matches)) {
            $value = str_replace('${' . $matches[1] . '}', $this->getEnv($matches[1]), $value);
        }

        return $value;
    }

    private function getEnv(string $name): string
    {
        $value = getenv($name);
        if ($value === false) {
            $value = getenv(str_replace('NEUCORE_', 'BRAVECORE_', $name));
        }

        if ((string) $value === '' && isset($this->config['env_var_defaults'][$name])) {
            $value = $this->config['env_var_defaults'][$name];
        }

        return (string) $value;
    }
}
