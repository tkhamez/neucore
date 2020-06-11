<?php

declare(strict_types=1);

namespace Neucore\Command;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Neucore\Api;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CorporationRepository;
use Neucore\Repository\WatchlistRepository;
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

class AutoWhitelist extends Command
{
    use LogOutput;
    use EsiRateLimited;

    const KEY_TOKEN = 'token';

    const KEY_IDS = 'ids';

    /**
     * @var Watchlist
     */
    private $watchlistService;

    /**
     * @var EsiData
     */
    private $esiData;

    /**
     * @var int
     */
    private $sleep;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var OAuthToken
     */
    private $tokenService;

    /**
     * @var WatchlistRepository
     */
    private $watchlistRepository;

    /**
     * @var CorporationRepository
     */
    private $corporationRepository;

    /**
     * @var int
     */
    private $numCorporations;

    /**
     * @var int
     */
    private $numCorporationsChecked;

    /**
     * @var int
     */
    private $numCorporationsWhitelisted;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        LoggerInterface $logger,
        Watchlist $watchlist,
        EsiData $esiData,
        ObjectManager $objectManager,
        OAuthToken $tokenService,
        StorageInterface $storage
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($storage);

        $this->watchlistService = $watchlist;
        $this->esiData = $esiData;
        $this->objectManager = $objectManager;
        $this->tokenService = $tokenService;
        $this->watchlistRepository = $repositoryFactory->getWatchlistRepository();
        $this->corporationRepository = $repositoryFactory->getCorporationRepository();
    }

    protected function configure(): void
    {
        $this->setName('auto-whitelist')
            ->setDescription('Adds personal alt corps to the watchlist corporation whitelist.')
            ->addArgument('id', InputArgument::OPTIONAL, 'The Watchlist ID.')
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each player and check',
                50
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->sleep = intval($input->getOption('sleep'));
        $id = intval($input->getArgument('id'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('Started "auto-whitelist"', false);

        if ($id > 0) {
            $ids = [$id];
        } else {
            $ids = array_map(function (\Neucore\Entity\Watchlist $watchlist) {
                return $watchlist->getId();
            }, $this->watchlistRepository->findBy([]));
        }

        foreach ($ids as $watchlistId) {
            $this->writeLine("  Processing watchlist $watchlistId", false);
            $this->numCorporations = 0;
            $this->numCorporationsChecked = 0;
            $this->numCorporationsWhitelisted = 0;
            $this->whitelist($watchlistId);
        }

        $this->writeLine('Finished "auto-whitelist"', false);
        return 0;
    }

    private function whitelist(int $id): void
    {
        $watchlist = $this->watchlistRepository->find($id);
        if ($watchlist === null) {
            $this->writeLine('    Watchlist not found.', false);
            return;
        }

        $players = $this->watchlistService->getRedFlagList($id, true, true); // include blacklist and whitelist
        $watchedCorporationIds = $this->watchlistService->getCorporationIds($id, 'alliance', 'corporation');

        $accountsData = $this->getAccountData($players, $watchedCorporationIds);

        $this->objectManager->clear(); // reduces memory usage a little bit

        $whitelist = $this->getWhitelist($accountsData);

        $this->writeLine(
            "    Corporations to check: {$this->numCorporations}, checked: {$this->numCorporationsChecked}, ".
            "whitelisted: {$this->numCorporationsWhitelisted}",
            false
        );

        $watchlist = $this->watchlistRepository->find($id); // read again because of "clear" above
        if ($watchlist === null) {
            $this->writeLine('    Watchlist not found.', false);
            return;
        }
        $this->saveWhitelist($watchlist, $whitelist);
    }

    /**
     * find relevant corporations for each account and store their character IDs
     *
     * @param Player[] $players
     * @param int[] $watchedCorporationIds
     * @return array
     */
    private function getAccountData(array $players, array $watchedCorporationIds)
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

                if (! isset($accountsData[$playerId][$corporationId])) {
                    $accountsData[$playerId][$corporationId] = [self::KEY_IDS => [], self::KEY_TOKEN => null];
                }
                $accountsData[$playerId][$corporationId][self::KEY_IDS][] = $character->getId();
                if (
                    $accountsData[$playerId][$corporationId][self::KEY_TOKEN] === null &&
                    $character->getValidToken() &&
                    in_array(Api::SCOPE_MEMBERSHIP, $character->getScopesFromToken())
                ) {
                    $accountsData[$playerId][$corporationId][self::KEY_TOKEN] = $character->createAccessToken();
                }
            }
            if (count($accountsData[$playerId]) === 0) {
                unset($accountsData[$playerId]);
            }

            $this->writeLine("    Collected data from player $playerId.");
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
    private function getWhitelist(array $accountsData): array
    {
        $whitelist = [];
        foreach ($accountsData as $corporations) {
            $this->numCorporations ++;
            foreach ($corporations as $corporationId => $characters) {
                if ($characters[self::KEY_TOKEN] === null) {
                    continue;
                }

                $this->checkErrorLimit();

                try {
                    $token = $this->tokenService->refreshAccessToken($characters[self::KEY_TOKEN]);
                } catch (IdentityProviderException $e) {
                    continue;
                }

                $members = $this->esiData->fetchCorporationMembers($corporationId, $token->getToken());

                if (count($members) > 0) { // <1 would be an ESI error
                    $this->numCorporationsChecked ++;

                    if (count(array_diff($members, $characters[self::KEY_IDS])) === 0) {
                        // all members are on this account
                        $whitelist[] = $corporationId;
                        $this->numCorporationsWhitelisted ++;
                    }
                }

                $this->writeLine("    Checked corporation $corporationId.");
                usleep($this->sleep * 1000);
            }
        }

        return $whitelist;
    }

    private function saveWhitelist(\Neucore\Entity\Watchlist $watchlist, array $whitelist): void
    {
        foreach ($watchlist->getWhitelistCorporations() as $corporationRemove) {
            if ($corporationRemove->getAutoWhitelist()) {
                $watchlist->removeWhitelistCorporation($corporationRemove);
            }
        }

        foreach ($whitelist as $corporationId) {
            $corporation = $this->corporationRepository->find($corporationId);
            if ($corporation) {
                $corporation->setAutoWhitelist(true);
                $watchlist->addWhitelistCorporation($corporation);
            }
        }

        if (! $this->objectManager->flush()) {
            $this->writeLine('    Failed to save list.', false);
        }
    }
}
