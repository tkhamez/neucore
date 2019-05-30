<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Service\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private $config;

    public function setUp()
    {
        $this->config = new Config([
            'env_var_defaults' => ['CONFIG_TEST_THREE' => 'default'],
            'key' => ['values'],
            'key2' => ['val1' => 1, 'val2' => 2],
            'envVars' => [
                'one' => '${CONFIG_TEST_ONE}',
                'two' => '${CONFIG_TEST_TWO}',
                'three' => '${CONFIG_TEST_THREE}'
            ],
        ]);
    }

    public function testReplaceEnvVars()
    {
        putenv("CONFIG_TEST_ONE=env-value");
        $this->assertSame(
            ['one' => 'env-value', 'two' => '', 'three' => 'default'],
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

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Read only.
     */
    public function testOffsetSet()
    {
        $this->config['nothing'] = 1;
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Read only.
     */
    public function testOffsetUnset()
    {
        unset($this->config['key']);
    }
}
