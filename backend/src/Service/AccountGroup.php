<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Data\CoreGroup;

class AccountGroup
{
    private RepositoryFactory $repositoryFactory;

    private \Doctrine\Persistence\ObjectManager $objectManager;

    public function __construct(RepositoryFactory $repositoryFactory, \Doctrine\Persistence\ObjectManager $objectManager)
    {
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManager = $objectManager;
    }

    /**
     * Checks if groups are deactivated for this player.
     */
    public function groupsDeactivated(Player $player, bool $ignoreDelay = false): bool
    {
        // managed account?
        if ($player->getStatus() === Player::STATUS_MANAGED) {
            return false;
        }

        // enabled?
        $requireToken = $this->repositoryFactory->getSystemVariableRepository()->findOneBy(
            ['name' => SystemVariable::GROUPS_REQUIRE_VALID_TOKEN]
        );
        if (!$requireToken || $requireToken->getValue() === '0') {
            return false;
        }

        // get configured alliances and corporations
        $sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();
        $allianceVar = $sysVarRepo->find(SystemVariable::ACCOUNT_DEACTIVATION_ALLIANCES);
        $corporationVar = $sysVarRepo->find(SystemVariable::ACCOUNT_DEACTIVATION_CORPORATIONS);
        if ($allianceVar === null && $corporationVar === null) {
            // Alliance and/or Corporation settings variable not found
            return false;
        }
        $alliances = array_map('intval', explode(',', $allianceVar ? $allianceVar->getValue() : ''));
        $corporations = array_map('intval', explode(',', $corporationVar ? $corporationVar->getValue() : ''));

        // check if player account has at least one character in one of the configured alliances or corporations
        if (!$player->hasCharacterInAllianceOrCorporation($alliances, $corporations)) {
            return false;
        }

        // get delay
        if ($ignoreDelay) {
            $hours = 0;
        } else {
            $delay = $this->repositoryFactory->getSystemVariableRepository()->findOneBy(
                ['name' => SystemVariable::ACCOUNT_DEACTIVATION_DELAY]
            );
            $hours = (int)$delay?->getValue();
        }

        if ($player->hasCharacterWithInvalidTokenOlderThan($hours)) {
            return true;
        }

        return false;
    }

    /**
     * @param Player $player
     * @return CoreGroup[]
     */
    public function getCoreGroups(Player $player): array
    {
        if ($this->groupsDeactivated($player)) { // do not ignore delay
            return [];
        }
        return $player->getCoreGroups();
    }

    /**
     * Remove player from group and corresponding application, should there be one.
     */
    public function removeGroupAndApplication(Player $player, Group $group): void
    {
        $player->removeGroup($group);

        // Remove application if one exists.
        $groupApplication = $this->repositoryFactory->getGroupApplicationRepository()->findOneBy([
            'player' => $player->getId(),
            'group' => $group->getId()
        ]);
        if ($groupApplication) {
            $this->objectManager->remove($groupApplication);
        }
    }
}
