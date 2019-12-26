<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CorporationRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Repository\WatchlistRepository;

class Watchlist
{
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

    public function getRedFlagList(int $id, bool $includeBlacklist = false): array
    {
        // get corporation IDs for the red list
        $corporationIds = $this->getCorporationIds($id, 'alliance', 'corporation');

        // get corporation IDs for the white list
        $whitelistCorporationIds = $this->getCorporationIds($id, 'whitelistAlliance', 'whitelistCorporation');

        $exemptPlayers = $this->getList($id, 'exemption');

        $playersFromCorporations = $this->playerRepository->findInCorporationsWithExcludes(
            $corporationIds,
            $exemptPlayers
        );

        $playersNotInWhitelistCorporations = $this->playerRepository->findNotInNpcCorporationsWithExcludes(
            array_unique(array_merge($corporationIds, $whitelistCorporationIds)),
            $exemptPlayers
        );
        $playersNotOnWhiteList = array_map(function (Player $player) {
            return $player->getId();
        }, $playersNotInWhitelistCorporations);

        $playersOnBlacklist = [];
        if (! $includeBlacklist) {
            $playersFromBlacklistCorporations = $this->playerRepository->findInCorporationsWithExcludes(
                $this->getCorporationIds($id, 'blacklistAlliance', 'blacklistCorporations'),
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
        foreach ($playersFromCorporations as $playerFromCorporations) {
            if (
                in_array($playerFromCorporations->getId(), $playersNotOnWhiteList) &&
                ! in_array($playerFromCorporations->getId(), $playersOnBlacklist)
            ) {
                $result[] = $playerFromCorporations->jsonSerialize(true);
            }
        }

        return $result;
    }

    public function getBlacklist(int $id): array
    {
        $exemptPlayers = $this->getList($id, 'exemption');

        $playersFromBlacklistCorporations = $this->playerRepository->findInCorporationsWithExcludes(
            $this->getCorporationIds($id, 'blacklistAlliance', 'blacklistCorporations'),
            $exemptPlayers
        );

        $playersRedListIds = array_map(function (array $player) {
            return $player['id'];
        }, $this->getRedFlagList($id, true));

        $result = [];
        foreach ($playersFromBlacklistCorporations as $playerFromBlacklistCorporations) {
            if (in_array($playerFromBlacklistCorporations->getId(), $playersRedListIds)) {
                $result[] = $playerFromBlacklistCorporations->jsonSerialize(true);
            }
        }

        return $result;
    }

    public function getList(int $id, string $type): array
    {
        $data = [];
        $watchlist = $this->watchlistRepository->find($id);

        if ($watchlist === null) {
            return $data;
        }

        if ($type === 'group') {
            $data = $watchlist->getGroups();
        } elseif ($type === 'alliance') {
            $data = $watchlist->getAlliances();
        } elseif ($type === 'corporation') {
            $data = $watchlist->getCorporations();
        } elseif ($type === 'exemption') {
            $data = array_map(function (Player $player) {
                return $player->jsonSerialize(true);
            }, $watchlist->getExemptions());
        } elseif ($type === 'blacklistCorporations') {
            $data = $watchlist->getBlacklistCorporations();
        } elseif ($type === 'blacklistAlliance') {
            $data = $watchlist->getBlacklistAlliances();
        } elseif ($type === 'whitelistCorporation') {
            $data = $watchlist->getWhitelistCorporations();
        } elseif ($type === 'whitelistAlliance') {
            $data = $watchlist->getWhitelistAlliances();
        }

        return $data;
    }

    /**
     * @return int[]
     */
    private function getCorporationIds(int $watchlistId, string $allianceList, string $corporationList): array
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
}
