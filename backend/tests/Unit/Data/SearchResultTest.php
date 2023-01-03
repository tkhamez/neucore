<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use Neucore\Data\SearchResult;
use PHPUnit\Framework\TestCase;

class SearchResultTest extends TestCase
{
    public function testJsonEncode()
    {
        $obj = new SearchResult(100, 'char name', 1, 'player name');

        $this->assertSame(
            [
                'characterId' => 100,
                'characterName' => 'char name',
                'playerId' => 1,
                'playerName' => 'player name',
            ],
            $obj->jsonSerialize()
        );
    }
}
