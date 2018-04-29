<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\CorporationRepository;
use Brave\Core\Entity\GroupRepository;
use Brave\Core\Entity\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;

class AutoGroupAssignment
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var CorporationRepository
     */
    private $corpRepo;

    /**
     * @var PlayerRepository
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

    public function __construct(
        EntityManagerInterface $em,
        CorporationRepository $corpRepo,
        GroupRepository $groupRepo,
        PlayerRepository $playerRepo
    ) {
        $this->em = $em;
        $this->corpRepo = $corpRepo;
        $this->groupRepo = $groupRepo;
        $this->playerRepo = $playerRepo;
    }

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
            $corpId = $char->getCorporation()->getId();
            if (isset($this->mapping[$corpId])) {
                $groupIds = array_merge($groupIds, $this->mapping[$corpId]);
            }
        }

        // find what to remove and what to add
        $hasIds = $player->getGroupIds();
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

        $this->flush();
    }

    private function loadMapping()
    {
        if ($this->mapping !== null) {
            return;
        }

        $this->mapping = [];
        foreach ($this->corpRepo->getAllWithGroups() as $corp) {
            $cid = $corp->getId();
            $this->mapping[$cid] = [];
            foreach ($corp->getGroups() as $group) {
                $this->mapping[$cid][] = $group->getId();
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
