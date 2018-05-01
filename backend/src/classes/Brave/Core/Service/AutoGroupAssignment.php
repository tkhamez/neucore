<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\CorporationRepository;
use Brave\Core\Entity\GroupRepository;
use Brave\Core\Entity\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AutoGroupAssignment
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var CorporationRepository
     */
    private $corpRepo;

    /**
     * @var GroupRepository
     */
    private $groupRepo;

    /**
     * @var PlayerRepository
     */
    private $playerRepo;

    /**
     * Corporation ID to group IDs mapping
     *
     * @var array
     */
    private $mapping;

    /**
     * All group IDs from the corporation -> group configuration.
     *
     * @var array
     */
    private $autoGroups;

    public function __construct(
        LoggerInterface $log,
        EntityManagerInterface $em,
        CorporationRepository $corpRepo,
        GroupRepository $groupRepo,
        PlayerRepository $playerRepo
    ) {
        $this->log = $log;
        $this->em = $em;
        $this->corpRepo = $corpRepo;
        $this->groupRepo = $groupRepo;
        $this->playerRepo = $playerRepo;
    }

    /**
     * Add and remove groups from the player.
     *
     * The assignment is made on the basis of the Group -> Group configuration.
     *
     * Only groups belonging to a corporation will be removed if the character
     * does not belong to that corporation.
     *
     * @param int $playerId
     * @return void|\Brave\Core\Entity\Player|NULL
     */
    public function assign(int $playerId)
    {
        $player = $this->playerRepo->find($playerId);
        if ($player === null) {
            return;
        }

        $this->loadMapping();

        // collect group IDs
        $groupIds = [];
        foreach ($player->getCharacters() as $char) {
            if ($char->getCorporation() === null) {
                continue;
            }
            $corpId = $char->getCorporation()->getId();
            if (isset($this->mapping[$corpId])) {
                $groupIds = array_merge($groupIds, $this->mapping[$corpId]);
            }
        }
        $groupIds = array_unique($groupIds);

        // find what to remove and what to add
        $hasIds = array_intersect($player->getGroupIds(), $this->autoGroups);
        $removeIds = array_diff($hasIds, $groupIds);
        $addIds = array_diff($groupIds, $hasIds);

        // remove groups
        foreach ($removeIds as $removeId) {
            $player->removeGroupById($removeId);
        }

        // add groups
        foreach ($addIds as $addId) {
            $addGroup = $this->groupRepo->find($addId);
            $player->addGroup($addGroup);
        }

        $player->setLastUpdate(new \DateTime());

        if ($this->flush()) {
            return $player;
        }
    }

    private function loadMapping()
    {
        if ($this->mapping !== null) {
            return;
        }

        $this->mapping = [];
        $this->autoGroups = [];
        foreach ($this->corpRepo->getAllWithGroups() as $corp) {
            $cid = $corp->getId();
            $this->mapping[$cid] = [];
            foreach ($corp->getGroups() as $group) {
                $gid = $group->getId();
                $this->mapping[$cid][] = $gid;
                if (! in_array($gid, $this->autoGroups)) {
                    $this->autoGroups[] = $gid;
                }
            }
        }
    }

    private function flush(): bool
    {
        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return false;
        }
        return true;
    }
}
