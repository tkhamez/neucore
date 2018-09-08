<?php declare(strict_types=1);

namespace Tests\Unit\Core;

use Brave\Core\Config;

class ConfigTest extends \PHPUnit\Framework\TestCase
{
    public function testGet()
    {
        $c = new Config(['key' => ['values']]);

        $this->assertNull($c->get('nothing'));
        $this->assertSame(['values'], $c->get('key'));
    }
}
