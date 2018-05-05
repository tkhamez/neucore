<?php declare(strict_types=1);

namespace Tests\Unit\Core;

use Brave\Core\Config;

class ConfigTest extends \PHPUnit\Framework\TestCase
{
    public function testGet()
    {
        $c = new Config(['key' => ['vals']]);

        $this->assertNull($c->get('nothing'));
        $this->assertSame(['vals'], $c->get('key'));
    }
}
