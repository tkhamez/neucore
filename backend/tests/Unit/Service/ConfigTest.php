<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Service\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private $config;

    protected function setUp(): void
    {
        $this->config = new Config([
            'env_var_defaults' => ['CONFIG_TEST_THREE' => 'default'],
            'key' => ['values'],
            'key2' => ['val1' => 1, 'val2' => 2],
            'envVars' => [
                'one' => 'One ${CONFIG_TEST_ONE}.',
                'two' => '${CONFIG_TEST_TWO}',
                'three' => '${CONFIG_TEST_THREE}'
            ],
        ]);
    }

    public function testReplaceEnvVars()
    {
        putenv("CONFIG_TEST_ONE=env-value");
        $this->assertSame(
            ['one' => 'One env-value.', 'two' => '', 'three' => 'default'],
            $this->config['envVars']
        );
    }

    public function testOffsetExists()
    {
        $this->assertFalse(isset($this->config['nothing']));
        $this->assertTrue(isset($this->config['key']));
    }

    public function testOffsetGet()
    {
        $this->assertNull($this->config['nothing']);
        $this->assertSame(['values'], $this->config['key']);
        $this->assertSame(1, $this->config['key2']['val1']);
    }

    public function testOffsetSet()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Read only.');

        $this->config['nothing'] = 1;
    }

    public function testOffsetUnset()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Read only.');

        unset($this->config['key']);
    }
}
