<?php

declare(strict_types=1);

namespace Neucore\Plugin\Core;

use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Data\CoreCharacter;

class Data implements DataInterface
{
    public function __construct(private RepositoryFactory $repositoryFactory)
    {
    }

    public function getCharacter(int $characterId): ?CoreCharacter
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);

        return $character?->toCoreCharacter();
    }

    public function getPlayerId(int $characterId): ?int
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);

        return $character?->getPlayer()->getId();
    }
}
