<?php

declare(strict_types=1);

namespace Neucore\Data;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['characterId', 'characterName', 'playerId', 'playerName'])]
class SearchResult implements \JsonSerializable
{
    public function __construct(
        #[OA\Property]
        public int $characterId,
        #[OA\Property]
        public string $characterName,
        #[OA\Property]
        public int $playerId,
        #[OA\Property]
        public string $playerName,
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'characterId' => $this->characterId,
            'characterName' => $this->characterName,
            'playerId' => $this->playerId,
            'playerName' => $this->playerName,
        ];
    }
}
