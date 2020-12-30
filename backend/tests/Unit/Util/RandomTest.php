<?php

declare(strict_types=1);

namespace Tests\Unit\Util;

use Neucore\Util\Random;
use PHPUnit\Framework\TestCase;

class RandomTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testString()
    {
        $string = Random::chars(12);
        $this->assertSame(12, strlen($string));
        $this->assertSame(1, preg_match('/^[0-9a-zA-Z]+$/', $string));
    }

    /**
     * @throws \Exception
     */
    public function testHex()
    {
        $hex1 = Random::hex(32);
        $this->assertSame(32, strlen($hex1));
        $this->assertSame(1, preg_match('/^[0-9a-f]+$/', $hex1));

        $hex2 = Random::hex(31);
        $this->assertSame(32, strlen($hex2));
    }
}
