<?php

declare(strict_types=1);

namespace Neucore\Service;

/**
 * Wraps the config array to make it injectable.
 *
 * @psalm-suppress MissingTemplateParam
 */
class Config implements \ArrayAccess
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->config);
    }

    public function offsetGet(mixed $offset): string|array|null
    {
        return $this->offsetExists($offset) ? $this->replaceEnvVars($this->config[$offset]) : null;
    }

    /**
     * @throws \BadMethodCallException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('Read only.');
    }

    /**
     * @throws \BadMethodCallException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('Read only.');
    }

    private function replaceEnvVars(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $k => $val) {
                $value[$k] = $this->replaceEnvVars($val);
            }
            return $value;
        }

        if (preg_match('/\${([A-Z\d_]+)}/', (string)$value, $matches)) {
            $value = str_replace('${' . $matches[1] . '}', $this->getEnv($matches[1]), $value);
        }

        return $value;
    }

    private function getEnv(string $name): string
    {
        $value = $_ENV[$name] ?? null;
        if ($value === null) {
            $legacyName = str_replace('NEUCORE_', 'BRAVECORE_', $name);
            $value = $_ENV[$legacyName] ?? null;
        }

        if ((string)$value === '' && isset($this->config['env_var_defaults'][$name])) {
            $value = $this->config['env_var_defaults'][$name];
        }

        return (string)$value;
    }
}
