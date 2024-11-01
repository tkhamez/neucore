<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\AllianceRepository;
use Neucore\Repository\CorporationRepository;
use Neucore\Repository\GroupRepository;

class AutoGroupAssignment
{
    private AllianceRepository $allianceRepo;

    private CorporationRepository $corpRepo;

    private GroupRepository $groupRepo;

    private AccountGroup $accountGroup;

    /**
     * Alliance ID to group IDs mapping.
     */
    private array $allianceMapping = [];

    /**
     * Corporation ID to group IDs mapping.
     */
    private array $corpMapping = [];

    /**
     * All group IDs from the corporation and alliance to group configuration.
     */
    private ?array $autoGroups = null;

    public function __construct(RepositoryFactory $repositoryFactory, AccountGroup $accountGroup)
    {
        $this->allianceRepo = $repositoryFactory->getAllianceRepository();
        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->groupRepo = $repositoryFactory->getGroupRepository();
        $this->accountGroup = $accountGroup;
    }

    /**
     * Adds default groups to account unless the status is "managed".
     */
    public function assignDefaultGroups(Player $player): void
    {
        if ($player->getStatus() === Player::STATUS_MANAGED) {
            return;
        }

        $defaultGroups = $this->groupRepo->findBy(['isDefault' => true]);
        foreach ($defaultGroups as $defaultGroup) {
            if (!$player->hasGroup($defaultGroup->getId())) {
                $player->addGroup($defaultGroup);
            }
        }
    }

    /**
     * Add and remove groups from the player unless the account status is "managed".
     *
     * The assignment is based on the group configuration of the
     * corporations and alliances.
     *
     * A player gets all groups that belongs to his corporation or
     * alliance.
     *
     * Only groups belonging to a corporation or alliance will be removed
     * from players when they are no longer a member of those corporations
     * or alliances.
     *
     * Does not flush the entity manager.
     *
     * @param Player $player An object attached to the entity manager
     */
    public function assign(Player $player): void
    {
        if ($player->getStatus() === Player::STATUS_MANAGED) {
            return;
        }

        $this->loadMapping();

        // collect groups that the player should have
        $groupIds = [];
        foreach ($player->getCharacters() as $char) {
            if ($char->getCorporation() === null) {
                continue;
            }

            $corpId = $char->getCorporation()->getId();
            if (isset($this->corpMapping[$corpId])) {
                $groupIds = array_merge($groupIds, $this->corpMapping[$corpId]);
            }

            if ($char->getCorporation()->getAlliance() !== null) {
                $allianceId = $char->getCorporation()->getAlliance()->getId();
                if (isset($this->allianceMapping[$allianceId])) {
                    $groupIds = array_merge($groupIds, $this->allianceMapping[$allianceId]);
                }
            }
        }
        $groupIds = array_unique($groupIds);

        // find what to remove and what to add
        $hasIds = array_intersect($player->getGroupIds(), $this->autoGroups ?? []);
        $removeIds = array_diff($hasIds, $groupIds);
        $addIds = array_diff($groupIds, $hasIds);

        // remove groups
        foreach ($removeIds as $removeId) {
            $removeGroup = $player->findGroupById($removeId);
            if ($removeGroup) {
                $this->accountGroup->removeGroupAndApplication($player, $removeGroup);
            }
        }

        // add groups
        foreach ($addIds as $addId) {
            $addGroup = $this->groupRepo->find($addId);
            if ($addGroup) {
                // Note: do not check Player::isAllowedMember here because that can only be checked once all
                // groups were added - and is checked in Account::updateGroups via self::checkRequiredGroups.
                $player->addGroup($addGroup);
            }
        }

        try {
            $player->setLastUpdate(new \DateTime());
        } catch (\Exception) {
            // ignore
        }
    }

    /**
     * Removes groups from player if they are
     * - not a member of at least one of the required groups.
     * - a member of any forbidden group.
     *
     * Does not flush the entity manager.
     */
    public function checkRequiredGroups(Player $player): void
    {
        do {
            $lastGroupCount = count($player->getGroups());
            foreach ($this->getGroupsOrderedByHasRequiredOrForbiddenGroups($player) as $group) {
                if (!$player->isAllowedMember($group)) {
                    $this->accountGroup->removeGroupAndApplication($player, $group);
                }
            }
        } while ($lastGroupCount !== count($player->getGroups()));
    }

    /**
     * @return Group[]
     */
    private function getGroupsOrderedByHasRequiredOrForbiddenGroups(Player $player): array
    {
        // If group A has a forbidden group B, and the player is currently a member of
        // group A and B, then group A must be removed unless group B is removed for another
        // reason. So it's important to check groups with forbidden groups last.

        $groups1 = [];
        $groups2 = [];
        $groups3 = [];
        $groups4 = [];

        foreach ($player->getGroups() as $group) {
            if (
                count($group->getRequiredGroups()) === 0 &&
                count($group->getForbiddenGroups()) === 0
            ) {
                $groups1[] = $group;
            } elseif (
                count($group->getRequiredGroups()) > 0 &&
                count($group->getForbiddenGroups()) === 0
            ) {
                $groups2[] = $group;
            } elseif (
                count($group->getRequiredGroups()) > 0 &&
                count($group->getForbiddenGroups()) > 0
            ) {
                $groups3[] = $group;
            } else { // 0 required groups, >0 forbidden groups
                $groups4[] = $group;
            }
        }

        return array_merge($groups1, $groups2, $groups3, $groups4);
    }

    private function loadMapping(): void
    {
        if ($this->autoGroups !== null) {
            return;
        }
        $this->autoGroups = [];

        $this->allianceMapping = $this->fillMaps($this->allianceRepo->getAllWithGroups());
        $this->corpMapping = $this->fillMaps($this->corpRepo->getAllWithGroups());
    }

    /**
     *
     * @param Corporation[]|Alliance[] $entities
     * @return array
     */
    private function fillMaps(array $entities): array
    {
        $map = [];

        foreach ($entities as $entity) {
            $eId = $entity->getId();
            $map[$eId] = [];
            foreach ($entity->getGroups() as $group) {
                $gId = $group->getId();
                if (! in_array($gId, $map)) {
                    $map[$eId][] = $gId;
                }
                if (!in_array($gId, $this->autoGroups ?? [])) {
                    $this->autoGroups[] = $gId;
                }
            }
        }

        return $map;
    }
}
