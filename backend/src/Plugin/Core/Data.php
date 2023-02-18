<?php

declare(strict_types=1);

namespace Neucore\Plugin\Core;

use Neucore\Entity\Character;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Data\CoreCharacter;

class Data implements DataInterface
{
    public function __construct(private RepositoryFactory $repositoryFactory)
    {
    }

    public function getCharacterIdsByCorporation(int $corporationId): ?array
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
        if (!$corporation) {
            return null;
        }

        return array_map(function (Character $character) {
            return $character->getId();
        }, $corporation->getCharacters());
    }

    public function getCharactersByCorporation(int $corporationId): ?array
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
        if (!$corporation) {
            return null;
        }

        return array_map(function (Character $character) {
            return $character->toCoreCharacter(false);
        }, $corporation->getCharacters());
    }

    public function getMemberTracking(int $corporationId): ?array
    {
        $repo = $this->repositoryFactory->getCorporationMemberRepository();

        $ids = $repo->fetchCorporationIds();
        if (!in_array($corporationId, $ids)) {
            return null;
        }

        $result = [];
        foreach ($repo->findMatching($corporationId) as $corpMember) {
            if ($coreMember = $corpMember->toCoreMemberTracking()) {
                $result[] = $coreMember;
            }
        }
        return $result;
    }

    public function getCharacter(int $characterId): ?CoreCharacter
    {
        return $this->repositoryFactory->getCharacterRepository()->find($characterId)?->toCoreCharacter();
    }

    public function getCharacterTokens(int $characterId): ?array
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if (!$character) {
            return null;
        }

        $esiTokens = [];
        foreach ($character->getEsiTokens() as $token) {
            if ($token->getEveLogin() && ($coreToken = $token->toCoreEsiToken(false))) {
                $esiTokens[] = $coreToken;
            }
        }
        return $esiTokens;
    }

    public function getPlayerId(int $characterId): ?int
    {
        return $this->repositoryFactory->getCharacterRepository()->find($characterId)?->getPlayer()->getId();
    }

    public function getEveLoginNames(): array
    {
        return array_map(function (EveLogin $eveLogin) {
            return $eveLogin->getName();
        }, $this->repositoryFactory->getEveLoginRepository()->findBy([]));
    }

    public function getLoginTokens(string $eveLoginName): ?array
    {
        if ($eveLoginName === EveLogin::NAME_DEFAULT) {
            return null;
        }

        $eveLogin = $this->repositoryFactory->getEveLoginRepository()->findOneBy(['name' => $eveLoginName]);
        if (!$eveLogin) {
            return null;
        }

        $result = [];
        foreach ($this->repositoryFactory->getEsiTokenRepository()->findBy(['eveLogin' => $eveLogin]) as $esiToken) {
            if ($coreToken = $esiToken->toCoreEsiToken(true)) {
                $result[] = $coreToken;
            }
        }

        return $result;
    }

    public function getGroups(): array
    {
        return array_map(function (Group $group) {
            return $group->toCoreGroup();
        }, $this->repositoryFactory->getGroupRepository()->findBy([]));
    }

    public function getCorporationGroups(int $corporationId): ?array
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
        if (!$corporation) {
            return null;
        }

        return array_map(function (Group $group) {
            return $group->toCoreGroup();
        }, $corporation->getGroups());
    }

    public function getAllianceGroups(int $allianceId): ?array
    {
        $alliance = $this->repositoryFactory->getAllianceRepository()->find($allianceId);
        if (!$alliance) {
            return null;
        }

        return array_map(function (Group $group) {
            return $group->toCoreGroup();
        }, $alliance->getGroups());
    }
}
