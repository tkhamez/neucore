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

    public function testHexToBytes()
    {
        $bytes = Random::pseudoRandomBytes(16);
        $this->assertSame(16, strlen($bytes));
    }
}
