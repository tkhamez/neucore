<?php

declare(strict_types=1);

namespace Tests\Unit\Plugin;

use Neucore\Plugin\CoreCharacter;
use PHPUnit\Framework\TestCase;

class CoreCharacterTest extends TestCase
{
    public function testConstruct()
    {
        $char1 = new CoreCharacter(200);
        $this->assertSame(200, $char1->id);
        $this->assertNull($char1->name);
        $this->assertNull($char1->ownerHash);
        $this->assertNull($char1->corporationId);
        $this->assertNull($char1->corporationName);
        $this->assertNull($char1->corporationTicker);
        $this->assertNull($char1->allianceId);
        $this->assertNull($char1->allianceName);
        $this->assertNull($char1->allianceTicker);

        $char2 = new CoreCharacter(100, 'char', 'hash', 10, 'corp', 'ticker', 1, 'alli', 'a-tick');
        $this->assertSame(100, $char2->id);
        $this->assertSame('char', $char2->name);
        $this->assertSame('hash', $char2->ownerHash);
        $this->assertSame(10, $char2->corporationId);
        $this->assertSame('corp', $char2->corporationName);
        $this->assertSame('ticker', $char2->corporationTicker);
        $this->assertSame(1, $char2->allianceId);
        $this->assertSame('alli', $char2->allianceName);
        $this->assertSame('a-tick', $char2->allianceTicker);
    }

    public function testCoreGroup()
    {
        $char = new CoreCharacter(200);
        $this->assertSame([], $char->groups);
    }
}
