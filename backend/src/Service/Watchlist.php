<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CorporationRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Repository\WatchlistRepository;

class Watchlist
{
    public const GROUP = 'group';

    public const MANAGER_GROUP = 'managerGroup';

    public const ALLIANCE = 'alliance';

    public const CORPORATION = 'corporation';

    public const EXEMPTION = 'exemption';

    public const KICKLIST_CORPORATION = 'kicklistCorporation';

    public const KICKLIST_ALLIANCE = 'kicklistAlliance';

    public const ALLOWLIST_CORPORATION = 'allowlistCorporation';

    public const ALLOWLIST_ALLIANCE = 'allowlistAlliance';

    private PlayerRepository $playerRepository;

    private WatchlistRepository $watchlistRepository;

    private CorporationRepository $corporationRepository;

    public function __construct(RepositoryFactory $repositoryFactory)
    {
        $this->playerRepository = $repositoryFactory->getPlayerRepository();
        $this->watchlistRepository = $repositoryFactory->getWatchlistRepository();
        $this->corporationRepository = $repositoryFactory->getCorporationRepository();
    }

    /**
     * @return Player[]
     */
    public function getWarningList(int $id, bool $includeKicklist = false, bool $includeAllowlist = false): array
    {
        // get corporation IDs for the warning list
        $corporationIds = $this->getCorporationIds($id, self::ALLIANCE, self::CORPORATION);

        // get corporation IDs for the allowlist
        if ($includeAllowlist) {
            $allowlistCorporationIds =  [];
        } else {
            $allowlistCorporationIds = $this->getCorporationIds(
                $id,
                self::ALLOWLIST_ALLIANCE,
                self::ALLOWLIST_CORPORATION,
            );
        }

        // get players on allowlist
        if ($includeAllowlist) {
            $exemptPlayers = [];
        } else {
            $exemptPlayers = $this->getExemptionList($id);
        }

        // get players in watched corporations
        $playersFromWatchedCorporations = $this->playerRepository->findInCorporationsWithExcludes(
            $corporationIds,
            $exemptPlayers,
        );

        // get players not on allowlist
        $playersNotInAllowlistCorporations = $this->playerRepository->findNotInNpcCorporationsWithExcludes(
            array_unique(array_merge($corporationIds, $allowlistCorporationIds)),
            $exemptPlayers,
        );
        $playersNotOnAllowList = array_map(function (Player $player) {
            return $player->getId();
        }, $playersNotInAllowlistCorporations);

        // get kicklist
        $playersOnKicklist = [];
        if (!$includeKicklist) {
            $playersFromKicklistCorporations = $this->playerRepository->findInCorporationsWithExcludes(
                $this->getCorporationIds($id, self::KICKLIST_ALLIANCE, self::KICKLIST_CORPORATION),
                $exemptPlayers,
            );
            $playersOnKicklist = array_map(function (Player $player) {
                return $player->getId();
            }, $playersFromKicklistCorporations);
        }

        // build result:
        // all accounts with characters from corporations to watch,
        // excluding all corporations from the allowlist
        // (which includes the corporations to watch, NPC corps and corporations manually added to the allowlist)
        // and optionally excluding accounts from the kicklist
        $result = [];
        foreach ($playersFromWatchedCorporations as $playerFromCorporations) {
            if (
                in_array($playerFromCorporations->getId(), $playersNotOnAllowList) &&
                ! in_array($playerFromCorporations->getId(), $playersOnKicklist)
            ) {
                $result[] = $playerFromCorporations;
            }
        }

        return $result;
    }

    public function getKicklist(int $id): array
    {
        $playersFromKicklistCorporations = $this->playerRepository->findInCorporationsWithExcludes(
            $this->getCorporationIds($id, self::KICKLIST_ALLIANCE, self::KICKLIST_CORPORATION),
            $this->getExemptionList($id),
        );

        $playersRedListIds = array_map(function (Player $player) {
            return $player->getId();
        }, $this->getWarningList($id, true));

        $result = [];
        foreach ($playersFromKicklistCorporations as $playerFromKicklistCorporations) {
            if (in_array($playerFromKicklistCorporations->getId(), $playersRedListIds)) {
                $result[] = $playerFromKicklistCorporations->jsonSerialize(true);
            }
        }

        return $result;
    }

    /**
     * @param int $id
     * @param string $type
     * @return Group[]|Alliance[]|Corporation[]|Player[]
     */
    public function getList(int $id, string $type): array
    {
        $data = [];
        $watchlist = $this->watchlistRepository->find($id);

        if ($watchlist === null) {
            return $data;
        }

        if ($type === self::GROUP) {
            $data = $watchlist->getGroups();
        } elseif ($type === self::MANAGER_GROUP) {
            $data = $watchlist->getManagerGroups();
        } elseif ($type === self::ALLIANCE) {
            $data = $watchlist->getAlliances();
        } elseif ($type === self::CORPORATION) {
            $data = $watchlist->getCorporations();
        } elseif ($type === self::EXEMPTION) {
            $data = $watchlist->getExemptions();
        } elseif ($type === self::KICKLIST_CORPORATION) {
            $data = $watchlist->getKicklistCorporations();
        } elseif ($type === self::KICKLIST_ALLIANCE) {
            $data = $watchlist->getKicklistAlliances();
        } elseif ($type === self::ALLOWLIST_CORPORATION) {
            $data = $watchlist->getAllowlistCorporations();
        } elseif ($type === self::ALLOWLIST_ALLIANCE) {
            $data = $watchlist->getAllowlistAlliances();
        }

        return $data;
    }

    /**
     * @return Player[]
     */
    public function getExemptionList(int $id): array
    {
        // @phpstan-ignore return.type
        return $this->getList($id, self::EXEMPTION);
    }

    /**
     * @return Corporation[]
     */
    public function getAllowlistCorporationList(int $id): array
    {
        // @phpstan-ignore return.type
        return $this->getList($id, self::ALLOWLIST_CORPORATION);
    }

    /**
     * @return int[]
     */
    public function getCorporationIds(int $watchlistId, string $allianceList, string $corporationList): array
    {
        // @phpstan-ignore argument.type
        $allianceIds = array_map(function (Alliance $alliance) {
            return $alliance->getId();
        }, $this->getList($watchlistId, $allianceList));
        $corporationIds1 = array_map(function (Corporation $corporation) {
            return $corporation->getId();
        }, $this->corporationRepository->getAllFromAlliances($allianceIds));
        // @phpstan-ignore argument.type
        $corporationIds2 = array_map(function (Corporation $corporation) {
            return $corporation->getId();
        }, $this->getList($watchlistId, $corporationList));

        return array_unique(array_merge($corporationIds1, $corporationIds2));
    }
}
