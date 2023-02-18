<?php

declare(strict_types=1);

namespace Neucore\Plugin\Core;

use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
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

    public function getAccountsByGroup(int $groupId): ?array
    {
        $group = $this->repositoryFactory->getGroupRepository()->find($groupId);
        if (!$group) {
            return null;
        }

        $result = [];
        foreach ($group->getPlayers() as $player) {
            if ($account = $player->toCoreAccount(false)) {
                $result[] = $account;
            }
        }
        return $result;
    }

    public function getAccountsByGroupManager(int $groupId): ?array
    {
        $group = $this->repositoryFactory->getGroupRepository()->find($groupId);
        if (!$group) {
            return null;
        }

        $result = [];
        foreach ($group->getManagers() as $player) {
            if ($account = $player->toCoreAccount(false)) {
                $result[] = $account;
            }
        }
        return $result;
    }

    public function getAccountsByRole(string $roleName): ?array
    {
        if ($roleName === Role::USER) {
            return null;
        }

        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $roleName]);
        if (!$role) {
            return null;
        }

        $result = [];
        foreach ($role->getPlayers() as $player) {
            if ($account = $player->toCoreAccount(false)) {
                $result[] = $account;
            }
        }
        return $result;
    }

    public function getAccount(int $playerId): ?CoreAccount
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if (!$player) {
            return null;
        }

        $coreAccount = $player->toCoreAccount();
        if (!$coreAccount) {
            return null; // no main
        }

        $coreAccount->groupsDeactivated = $this->accountGroup->groupsDeactivated($player);

        return $coreAccount;
    }

    public function getMain(int $playerId): ?CoreCharacter
    {
        return $this->repositoryFactory->getPlayerRepository()->find($playerId)?->getMain()?->toCoreCharacter();
    }

    public function getCharacters(int $playerId): ?array
    {
        return $this->repositoryFactory->getPlayerRepository()->find($playerId)?->getCoreCharacters();
    }

    public function getMemberGroups(int $playerId): ?array
    {
        return $this->repositoryFactory->getPlayerRepository()->find($playerId)?->getCoreGroups();
    }

    public function groupsDeactivated(int $playerId): ?bool
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if (!$player) {
            return null;
        }

        return $this->accountGroup->groupsDeactivated($player);
    }

    public function getManagerGroups(int $playerId): ?array
    {
        return $this->repositoryFactory->getPlayerRepository()->find($playerId)?->getManagerCoreGroups();
    }

    public function getRoles(int $playerId): ?array
    {
        return $this->repositoryFactory->getPlayerRepository()->find($playerId)?->getCoreRoles();
    }

    public function getRemovedCharacters(int $playerId): ?array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if (!$player) {
            return null;
        }

        return array_map(function (RemovedCharacter $char) {
            return $char->toCoreMovedCharacter();
        }, $player->getRemovedCharacters());
    }

    public function getIncomingCharacters(int $playerId): ?array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if (!$player) {
            return null;
        }

        return array_map(function (RemovedCharacter $char) {
            return $char->toCoreMovedCharacter();
        }, $player->getIncomingCharacters());
    }
}
