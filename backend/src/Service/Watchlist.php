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
    const GROUP = 'group';

    const ALLIANCE = 'alliance';

    const CORPORATION = 'corporation';

    const EXEMPTION = 'exemption';

    const BLACKLIST_CORPORATION = 'blacklistCorporation';

    const BLACKLIST_ALLIANCE = 'blacklistAlliance';

    const WHITELIST_CORPORATION = 'whitelistCorporation';

    const WHITELIST_ALLIANCE = 'whitelistAlliance';

    /**
     * @var PlayerRepository
     */
    private $playerRepository;

    /**
     * @var WatchlistRepository
     */
    private $watchlistRepository;

    /**
     * @var CorporationRepository
     */
    private $corporationRepository;

    public function __construct(RepositoryFactory $repositoryFactory)
    {
        $this->playerRepository = $repositoryFactory->getPlayerRepository();
        $this->watchlistRepository = $repositoryFactory->getWatchlistRepository();
        $this->corporationRepository = $repositoryFactory->getCorporationRepository();
    }

    /**
     * @return Player[]
     */
    public function getRedFlagList(int $id, bool $includeBlacklist = false, bool $includeWhitelist = false): array
    {
        // get corporation IDs for the red list
        $corporationIds = $this->getCorporationIds($id, self::ALLIANCE, self::CORPORATION);

        // get corporation IDs for the white list
        if ($includeWhitelist) {
            $whitelistCorporationIds =  [];
        } else {
            $whitelistCorporationIds = $this->getCorporationIds(
                $id,
                self::WHITELIST_ALLIANCE,
                self::WHITELIST_CORPORATION
            );
        }

        // get whitelisted players
        if ($includeWhitelist) {
            $exemptPlayers = [];
        } else {
            $exemptPlayers = $this->getExemptionList($id);
        }

        // get players in watched corporations
        $playersFromWatchedCorporations = $this->playerRepository->findInCorporationsWithExcludes(
            $corporationIds,
            $exemptPlayers
        );

        // get players not on whitelist
        $playersNotInWhitelistCorporations = $this->playerRepository->findNotInNpcCorporationsWithExcludes(
            array_unique(array_merge($corporationIds, $whitelistCorporationIds)),
            $exemptPlayers
        );
        $playersNotOnWhiteList = array_map(function (Player $player) {
            return $player->getId();
        }, $playersNotInWhitelistCorporations);

        // get blacklist
        $playersOnBlacklist = [];
        if (! $includeBlacklist) {
            $playersFromBlacklistCorporations = $this->playerRepository->findInCorporationsWithExcludes(
                $this->getCorporationIds($id, self::BLACKLIST_ALLIANCE, self::BLACKLIST_CORPORATION),
                $exemptPlayers
            );
            $playersOnBlacklist = array_map(function (Player $player) {
                return $player->getId();
            }, $playersFromBlacklistCorporations);
        }

        // build result:
        // all accounts with characters from corporations to watch,
        // excluding all white listed corporations
        // (which includes the corporations to watch, NPC corps and manually whitelisted corporations)
        // and optionally excluding blacklisted accounts
        $result = [];
        foreach ($playersFromWatchedCorporations as $playerFromCorporations) {
            if (
                in_array($playerFromCorporations->getId(), $playersNotOnWhiteList) &&
                ! in_array($playerFromCorporations->getId(), $playersOnBlacklist)
            ) {
                $result[] = $playerFromCorporations;
            }
        }

        return $result;
    }

    public function getBlacklist(int $id): array
    {
        $playersFromBlacklistCorporations = $this->playerRepository->findInCorporationsWithExcludes(
            $this->getCorporationIds($id, self::BLACKLIST_ALLIANCE, self::BLACKLIST_CORPORATION),
            $this->getExemptionList($id)
        );

        $playersRedListIds = array_map(function (Player $player) {
            return $player->getId();
        }, $this->getRedFlagList($id, true));

        $result = [];
        foreach ($playersFromBlacklistCorporations as $playerFromBlacklistCorporations) {
            if (in_array($playerFromBlacklistCorporations->getId(), $playersRedListIds)) {
                $result[] = $playerFromBlacklistCorporations->jsonSerialize(true);
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
        } elseif ($type === self::ALLIANCE) {
            $data = $watchlist->getAlliances();
        } elseif ($type === self::CORPORATION) {
            $data = $watchlist->getCorporations();
        } elseif ($type === self::EXEMPTION) {
            $data = $watchlist->getExemptions();
        } elseif ($type === self::BLACKLIST_CORPORATION) {
            $data = $watchlist->getBlacklistCorporations();
        } elseif ($type === self::BLACKLIST_ALLIANCE) {
            $data = $watchlist->getBlacklistAlliances();
        } elseif ($type === self::WHITELIST_CORPORATION) {
            $data = $watchlist->getWhitelistCorporations();
        } elseif ($type === self::WHITELIST_ALLIANCE) {
            $data = $watchlist->getWhitelistAlliances();
        }

        return $data;
    }

    /**
     * @return int[]
     */
    public function getCorporationIds(int $watchlistId, string $allianceList, string $corporationList): array
    {
        $allianceIds = array_map(function (Alliance $alliance) {
            return $alliance->getId();
        }, $this->getList($watchlistId, $allianceList));
        $corporationIds1 = array_map(function (Corporation $corporation) {
            return $corporation->getId();
        }, $this->corporationRepository->getAllFromAlliances($allianceIds));
        $corporationIds2 = array_map(function (Corporation $corporation) {
            return $corporation->getId();
        }, $this->getList($watchlistId, $corporationList));

        return array_unique(array_merge($corporationIds1, $corporationIds2));
    }

    /**
     * @param int $id
     * @return Player[]
     */
    private function getExemptionList(int $id): array
    {
        return $this->getList($id, self::EXEMPTION);
    }
}
