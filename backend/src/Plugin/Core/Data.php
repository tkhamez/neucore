<?php

declare(strict_types=1);

namespace Neucore\Plugin\Core;

use Neucore\Entity\Character;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Data\CoreCharacter;

class Data implements DataInterface
{
    public function __construct(private RepositoryFactory $repositoryFactory)
    {
    }

    public function getCharactersByCorporation(int $corporationId): array
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
        if (!$corporation) {
            return [];
        }

        return array_map(function (Character $character) {
            return new CoreCharacter($character->getId(), $character->getPlayer()->getId());
        }, $corporation->getCharacters());
    }

    public function getCharacter(int $characterId): ?CoreCharacter
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);

        return $character?->toCoreCharacter();
    }

    public function getCharacterTokens(int $characterId): array
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if (!$character) {
            return [];
        }

        $esiTokens = [];
        foreach ($character->getEsiTokens() as $token) {
            if ($token->getEveLogin()) {
                $esiTokens[] = $token->toCoreEsiToken(false);
            }
        }

        return $esiTokens;
    }

    public function getPlayerId(int $characterId): ?int
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);

        return $character?->getPlayer()->getId();
    }

    public function getEveLoginNames(): array
    {
        return array_map(function (EveLogin $eveLogin) {
            return $eveLogin->getName();
        }, $this->repositoryFactory->getEveLoginRepository()->findBy([]));
    }

    public function getLoginTokens(string $eveLoginName): array
    {
        if ($eveLoginName === EveLogin::NAME_DEFAULT) {
            return [];
        }

        $eveLogin = $this->repositoryFactory->getEveLoginRepository()->findOneBy(['name' => $eveLoginName]);
        if (!$eveLogin) {
            return [];
        }

        return array_map(function (EsiToken $esiToken) {
            return $esiToken->toCoreEsiToken(true);
        }, $this->repositoryFactory->getEsiTokenRepository()->findBy(['eveLogin' => $eveLogin]));
    }

    public function getGroups(): array
    {
        return array_map(function (Group $group) {
            return $group->toCoreGroup();
        }, $this->repositoryFactory->getGroupRepository()->findBy([]));
    }
}
