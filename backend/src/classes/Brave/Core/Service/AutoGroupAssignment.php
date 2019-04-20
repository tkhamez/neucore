<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\Player;
use Brave\Core\Factory\RepositoryFactory;
use Psr\Log\LoggerInterface;

class AutoGroupAssignment
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var \Brave\Core\Repository\AllianceRepository
     */
    private $allianceRepo;

    /**
     * @var \Brave\Core\Repository\CorporationRepository
     */
    private $corpRepo;

    /**
     * @var \Brave\Core\Repository\GroupRepository
     */
    private $groupRepo;

    /**
     * @var \Brave\Core\Repository\PlayerRepository
     */
    private $playerRepo;

    /**
     * @var LoggerInterface
     */
    private $log;

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

    public function __construct(
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $logger
    ) {
        $this->objectManager = $objectManager;
        $this->allianceRepo = $repositoryFactory->getAllianceRepository();
        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->groupRepo = $repositoryFactory->getGroupRepository();
        $this->playerRepo = $repositoryFactory->getPlayerRepository();
        $this->log = $logger;
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
     */
    public function assign(int $playerId): bool
    {
        $player = $this->playerRepo->find($playerId);
        if ($player === null) {
            return false;
        }
        if ($player->getStatus() === Player::STATUS_MANAGED) {
            return true;
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

            if ($char->getCorporation()->getAlliance()) {
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
                $this->log->debug(
                    'AutoGroupAssignment: removed group ' . $removeGroup->getName() . ' [' . $removeId . '] ' .
                    'from player ' . $player->getName() . ' [' . $player->getId() . ']'
                );
            }
        }

        // add groups
        foreach ($addIds as $addId) {
            $addGroup = $this->groupRepo->find($addId);
            if ($addGroup) {
                $player->addGroup($addGroup);
                $this->log->debug(
                    'AutoGroupAssignment: added group ' . $addGroup->getName() . ' [' . $addId . '] ' .
                    'to player ' . $player->getName() . ' [' . $player->getId() . ']'
                );
            }
        }

        $player->setLastUpdate(date_create());

        if (! $this->objectManager->flush()) {
            return false;
        }

        return true;
    }

    public function checkRequiredGroups(int $playerId): bool
    {
        $player = $this->playerRepo->find($playerId);
        if ($player === null) {
            return false;
        }

        $lastGroupCount = 0;
        while ($lastGroupCount !== count($player->getGroups())) {
            $groups = $player->getGroups();
            foreach ($groups as $group) {
                foreach ($group->getRequiredGroups() as $requiredGroup) {
                    if (! $player->hasGroup($requiredGroup->getId())) {
                        $player->removeGroup($group);
                        break;
                    }
                }
            }
            $lastGroupCount = count($player->getGroups());
        }

        if (! $this->objectManager->flush()) {
            return false;
        }
        return true;
    }

    private function loadMapping()
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
