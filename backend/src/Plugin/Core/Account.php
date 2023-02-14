<?php

declare(strict_types=1);

namespace Neucore\Plugin\Core;

use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Data\CoreAccount;
use Neucore\Plugin\Data\CoreCharacter;
use Neucore\Service\AccountGroup;

class Account implements AccountInterface
{
    public function __construct(
        private RepositoryFactory $repositoryFactory,
        private AccountGroup $accountGroup,
    ) {
    }

    public function getAccount(int $playerId): ?CoreAccount
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if (!$player) {
            return null;
        }

        $coreAccount = $player->toCoreAccount();
        if (!$coreAccount) {
            return null;
        }

        $coreAccount->groupsDeactivated = $this->accountGroup->groupsDeactivated($player);

        return $coreAccount;
    }

    public function getMain(int $playerId): ?CoreCharacter
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);

        return $player?->getMain()?->toCoreCharacter();
    }

    public function getCharacters(int $playerId): array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if (!$player) {
            return [];
        }

        return $player->getCoreCharacters();
    }

    public function getMemberGroups(int $playerId): array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if (!$player) {
            return [];
        }

        return $player->getCoreGroups();
    }

    public function groupsDeactivated(int $playerId): bool
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if (!$player) {
            return false;
        }

        return $this->accountGroup->groupsDeactivated($player);
    }

    public function getManagerGroups(int $playerId): array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if (!$player) {
            return [];
        }

        return $player->getManagerCoreGroups();
    }

    public function getRoles(int $playerId): array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if (!$player) {
            return [];
        }

        return $player->getCoreRoles();
    }
}
