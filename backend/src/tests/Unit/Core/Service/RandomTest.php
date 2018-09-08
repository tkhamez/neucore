<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Service\Random;

class RandomTest extends \PHPUnit\Framework\TestCase
{
    public function testBytes()
    {
        $bytes = Random::bytes(16);
        $this->assertSame(16, strlen($bytes));
    }

    public function testPseudoRandomBytes()
    {
        $bytes = Random::pseudoRandomBytes(16);
        $this->assertSame(16, strlen($bytes));
    }

    public function testHex()
    {
        $hex1 = Random::hex(32);
        $this->assertSame(32, strlen($hex1));

        $hex2 = Random::hex(31);
        $this->assertSame(32, strlen($hex2));
    }
}
