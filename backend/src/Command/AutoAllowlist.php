<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EsiData;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use Neucore\Service\Watchlist;
use Neucore\Command\Traits\EsiRateLimited;
use Neucore\Storage\StorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AutoAllowlist extends Command
{
    use LogOutput;
    use EsiRateLimited;

    private const KEY_TOKEN_ID = 'token_id';

    private const KEY_CHAR_IDS = 'char_ids';

    private Watchlist $watchlistService;

    private EsiData $esiData;

    private ObjectManager $objectManager;

    private OAuthToken $tokenService;

    private RepositoryFactory $repositoryFactory;

    private int $sleep = 50;

    private int $numCorporations = 0;

    private int $numCorporationsChecked = 0;

    private int $numCorporationsAllowed = 0;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        LoggerInterface $logger,
        Watchlist $watchlist,
        EsiData $esiData,
        ObjectManager $objectManager,
        OAuthToken $tokenService,
        StorageInterface $storage,
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($storage, $logger);

        $this->watchlistService = $watchlist;
        $this->esiData = $esiData;
        $this->objectManager = $objectManager;
        $this->tokenService = $tokenService;
        $this->repositoryFactory = $repositoryFactory;
    }

    protected function configure(): void
    {
        $this->setName('auto-allowlist')
            ->setDescription('Adds personal alt corps to the watchlist corporation allowlist.')
            ->addArgument('id', InputArgument::OPTIONAL, 'The Watchlist ID.')
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each player and check',
                $this->sleep,
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->sleep = intval($input->getOption('sleep'));
        $id = intval($input->getArgument('id'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('Started "auto-allowlist"', false);

        if ($id > 0) {
            $ids = [$id];
        } else {
            $ids = array_map(function (\Neucore\Entity\Watchlist $watchlist) {
                return $watchlist->getId();
            }, $this->repositoryFactory->getWatchlistRepository()->findBy([]));
        }

        foreach ($ids as $watchlistId) {
            $this->writeLine("  Processing watchlist $watchlistId", false);
            $this->numCorporations = 0;
            $this->numCorporationsChecked = 0;
            $this->numCorporationsAllowed = 0;
            $this->allow($watchlistId);
        }

        $this->writeLine('Finished "auto-allowlist"', false);
        return 0;
    }

    private function allow(int $id): void
    {
        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($id);
        if ($watchlist === null) {
            $this->writeLine('    Watchlist not found.', false);
            return;
        }

        $players = $this->watchlistService->getWarningList($id, true, true); // include kicklist and allowlist
        $watchedCorporationIds = $this->watchlistService->getCorporationIds(
            $id,
            Watchlist::ALLIANCE,
            Watchlist::CORPORATION,
        );

        $accountsData = $this->getAccountData($players, $watchedCorporationIds);
        $this->objectManager->clear(); // reduces memory usage a little bit
        $allowlist = $this->getAllowlist($accountsData);

        $this->writeLine(
            "    Corporations to check: $this->numCorporations, checked: $this->numCorporationsChecked, " .
            "allowlist: $this->numCorporationsAllowed",
            false,
        );

        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($id); // entity manager was cleared
        if ($watchlist === null) {
            $this->writeLine('    Watchlist not found.', false);
            return;
        }
        $this->saveAllowlist($watchlist, $allowlist);
    }

    /**
     * find relevant corporations for each account and store their character IDs
     *
     * @param Player[] $players
     * @param int[] $watchedCorporationIds
     * @return array
     */
    private function getAccountData(array $players, array $watchedCorporationIds): array
    {
        $accountsData = [];
        $corporations = [];
        foreach ($players as $player) {
            $playerId = $player->getId();
            $accountsData[$playerId] = [];
            foreach ($player->getCharacters() as $character) {
                if ($character->getCorporation() === null) {
                    continue;
                }

                $corporationId = $character->getCorporation()->getId();

                if (in_array($corporationId, $watchedCorporationIds) || $corporationId <= 2000000) {
                    // one of the watched corporations or NPC corp
                    continue;
                }

                // collect corporations and check if they are already on another account
                if (isset($corporations[$corporationId]) && $corporations[$corporationId] !== $player->getId()) {
                    // no need to check corporation if it has members from several accounts
                    continue;
                }
                $corporations[$corporationId] = $player->getId();

                if (!isset($accountsData[$playerId][$corporationId])) {
                    $accountsData[$playerId][$corporationId] = [
                        self::KEY_CHAR_IDS => [],
                        self::KEY_TOKEN_ID => null,
                    ];
                }
                $accountsData[$playerId][$corporationId][self::KEY_CHAR_IDS][] = $character->getId();
                $esiToken = $character->getEsiToken(EveLogin::NAME_DEFAULT);
                if (
                    $accountsData[$playerId][$corporationId][self::KEY_TOKEN_ID] === null &&
                    $esiToken !== null &&
                    $esiToken->getValidToken() &&
                    in_array(EveLogin::SCOPE_MEMBERSHIP, $this->tokenService->getScopesFromToken($esiToken))
                ) {
                    $accountsData[$playerId][$corporationId][self::KEY_TOKEN_ID] = $esiToken->getId();
                }
            }
            if (empty($accountsData[$playerId])) {
                unset($accountsData[$playerId]);
            }

            $this->writeLine("    Collected data from player $playerId");
            usleep($this->sleep * 1000);
        }

        return $accountsData;
    }

    /**
     * fetch members of all corporations and check against characters on account
     *
     * @param array $accountsData
     * @return int[]
     */
    private function getAllowlist(array $accountsData): array
    {
        $allowlist = [];
        foreach ($accountsData as $corporations) {
            foreach ($corporations as $corporationId => $characters) {
                $this->numCorporations++;
                if ($characters[self::KEY_TOKEN_ID] === null) {
                    $this->writeLine("    No token for corporation $corporationId");
                    continue;
                }

                $this->checkForErrors();

                $tokenId = $characters[self::KEY_TOKEN_ID];
                $esiToken = $this->repositoryFactory->getEsiTokenRepository()->find($tokenId);
                if (!$esiToken || !$esiToken->getCharacter()) {
                    continue;
                }
                $token = $this->tokenService->updateEsiToken($esiToken);
                if (!$token) {
                    continue;
                }

                $members = $this->esiData->fetchCorporationMembers(
                    $corporationId,
                    $token->getToken(),
                    $esiToken->getCharacter()->getId()
                );
                if (empty($members)) { // ESI error
                    $this->writeLine(
                        "    Invalid token for $corporationId from " . $esiToken->getCharacter()->getId(),
                    );
                } else {
                    $this->numCorporationsChecked++;

                    if (empty(array_diff($members, $characters[self::KEY_CHAR_IDS]))) {
                        // all members are on this account
                        $allowlist[] = $corporationId;
                        $this->numCorporationsAllowed++;
                    }
                }

                $this->writeLine("    Checked corporation $corporationId");
                usleep($this->sleep * 1000);
            }
        }

        return $allowlist;
    }

    private function saveAllowlist(\Neucore\Entity\Watchlist $watchlist, array $allowlist): void
    {
        foreach ($watchlist->getAllowlistCorporations() as $corporationRemove) {
            if ($corporationRemove->getAutoAllowlist()) {
                $watchlist->removeAllowlistCorporation($corporationRemove);
            }
        }

        foreach ($allowlist as $corporationId) {
            $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
            if ($corporation) {
                $corporation->setAutoAllowlist(true);
                $watchlist->addAllowlistCorporation($corporation);
            }
        }

        if (!$this->objectManager->flush2()) {
            $this->writeLine('    Failed to save list.', false);
        }
    }
}
