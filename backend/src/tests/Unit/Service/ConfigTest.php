<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Service\Config;

class ConfigTest extends \PHPUnit\Framework\TestCase
{
    public function testGet()
    {
        $c = new Config(['key' => ['values'], 'key2' => ['val1' => 1, 'val2' => 2]]);

        $this->assertNull($c->get('nothing'));
        $this->assertSame(['values'], $c->get('key'));
        $this->assertSame(1, $c->get('key2', 'val1'));
    }
}
