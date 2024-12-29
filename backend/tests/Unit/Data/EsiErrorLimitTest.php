<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use Neucore\Data\EsiErrorLimit;
use PHPUnit\Framework\TestCase;

class EsiErrorLimitTest extends TestCase
{
    public function testJsonEncode()
    {
        $obj = new EsiErrorLimit(1673009940, 10, 50);

        $this->assertSame(
            ['updated' => 1673009940, 'remain' => 10, 'reset' => 50],
            json_decode((string) json_encode($obj), true),
        );
    }

    public function testFromJson()
    {
        $obj1 = EsiErrorLimit::fromJson('invalid');
        $this->assertNull($obj1->updated);
        $this->assertNull($obj1->remain);
        $this->assertNull($obj1->reset);

        $obj2 = EsiErrorLimit::fromJson((string) json_encode(new EsiErrorLimit(1673009940, 10, 50)));
        $this->assertSame(1673009940, $obj2->updated);
        $this->assertSame(10, $obj2->remain);
        $this->assertSame(50, $obj2->reset);

    }
}
