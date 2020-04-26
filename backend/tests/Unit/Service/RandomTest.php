<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Service\Random;
use PHPUnit\Framework\TestCase;

class RandomTest extends TestCase
{
    public function testBytes()
    {
        $bytes = Random::bytes(16);
        $this->assertSame(16, strlen($bytes));
    }

    public function testInt()
    {
        $int = Random::int(0, 40);
        $this->assertGreaterThanOrEqual(0, $int);
        $this->assertLessThanOrEqual(40, $int);
    }

    public function testString()
    {
        $string = Random::chars(12);
        $this->assertSame(12, strlen($string));
        $this->assertSame(1, preg_match('/^[0-9a-zA-Z]+$/', $string));
    }

    public function testHex()
    {
        $hex1 = Random::hex(32);
        $this->assertSame(32, strlen($hex1));
        $this->assertSame(1, preg_match('/^[0-9a-f]+$/', $hex1));

        $hex2 = Random::hex(31);
        $this->assertSame(32, strlen($hex2));
    }
}
