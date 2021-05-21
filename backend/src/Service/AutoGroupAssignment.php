<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\AllianceRepository;
use Neucore\Repository\CorporationRepository;
use Neucore\Repository\GroupRepository;

class AutoGroupAssignment
{
    /**
     * @var AllianceRepository
     */
    private $allianceRepo;

    /**
     * @var CorporationRepository
     */
    private $corpRepo;

    /**
     * @var GroupRepository
     */
    private $groupRepo;

    /**
     * Alliance ID to group IDs mapping.
     *
     * @var array
     */
    private $allianceMapping;

    /**
     * Corporation ID to group IDs mapping.
     *
     * @var array
     */
    private $corpMapping;

    /**
     * All group IDs from the corporation and alliance to group configuration.
     *
     * @var array
     */
    private $autoGroups;

    public function __construct(RepositoryFactory $repositoryFactory)
    {
        $this->allianceRepo = $repositoryFactory->getAllianceRepository();
        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->groupRepo = $repositoryFactory->getGroupRepository();
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
     * Only groups belonging to a company or alliance will be removed
     * from a player when he no longer is a member of that corporation
     * or alliance.
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
        $hasIds = array_intersect($player->getGroupIds(), $this->autoGroups);
        $removeIds = array_diff($hasIds, $groupIds);
        $addIds = array_diff($groupIds, $hasIds);

        // remove groups
        foreach ($removeIds as $removeId) {
            $removeGroup = $player->findGroupById($removeId);
            if ($removeGroup) {
                $player->removeGroup($removeGroup);
            }
        }

        // add groups
        foreach ($addIds as $addId) {
            $addGroup = $this->groupRepo->find($addId);
            if ($addGroup) {
                $player->addGroup($addGroup);
            }
        }

        try {
            $player->setLastUpdate(new \DateTime());
        } catch (\Exception $e) {
            // ignore
        }
    }

    /**
     * Removes groups from player if they are not a member of at least one of the required groups.
     *
     * Does not flush the entity manager.
     */
    public function checkRequiredGroups(Player $player): void
    {
        $lastGroupCount = 0;
        while ($lastGroupCount !== count($player->getGroups())) {
            $groups = $player->getGroups();
            foreach ($groups as $group) {
                if (!$player->isAllowedMember($group)) {
                    $player->removeGroup($group);
                }
            }
            $lastGroupCount = count($player->getGroups());
        }
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
                if (! in_array($gId, $this->autoGroups)) {
                    $this->autoGroups[] = $gId;
                }
            }
        }

        return $map;
    }
}
