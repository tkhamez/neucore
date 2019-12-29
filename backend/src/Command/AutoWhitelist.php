<?php

declare(strict_types=1);

namespace Neucore\Command;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CorporationRepository;
use Neucore\Repository\WatchlistRepository;
use Neucore\Service\EsiData;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use Neucore\Service\Watchlist;
use Neucore\Traits\EsiRateLimited;
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

    /**
     * @var Watchlist
     */
    private $watchlist;

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

    public function __construct(
        RepositoryFactory $repositoryFactory,
        LoggerInterface $logger,
        Watchlist $watchlist,
        EsiData $esiData,
        ObjectManager $objectManager,
        OAuthToken $tokenService
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($repositoryFactory->getSystemVariableRepository());

        $this->watchlist = $watchlist;
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
            ->addArgument('id', InputArgument::REQUIRED, 'The Watchlist ID.')
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each check',
                50
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->sleep = intval($input->getOption('sleep'));
        $id = intval($input->getArgument('id'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('auto-whitelist start.', false);

        $watchlist = $this->watchlistRepository->find($id);
        if ($watchlist === null) {
            $this->writeLine('Watchlist not found.', false);
            return 0;
        }

        $players = $this->watchlist->getRedFlagList(1, true, true);
        $watchedCorporationIds = $this->watchlist->getCorporationIds(1, 'alliance', 'corporation');

        $accountsData = $this->getAccountData($players, $watchedCorporationIds);

        $this->objectManager->clear(); // free memory TODO needed?

        $whitelist = $this->getWhitelist($accountsData);

        $watchlist = $this->watchlistRepository->find($id); // read again because of "clear" above
        $this->saveWhitelist($watchlist, $whitelist);

        $this->writeLine('auto-whitelist end.', false);
        return 0;
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
        foreach ($players as $player) {
            $playerId = $player->getId();
            $accountsData[$playerId] = [];
            foreach ($player->getCharacters() as $character) {
                if (! $character->getCorporation()) {
                    continue;
                }

                $corporationId = $character->getCorporation()->getId();

                if (in_array($corporationId, $watchedCorporationIds) || $corporationId <= 2000000) {
                    // one of the watched corporations or NPC corp
                    continue;
                }

                if (! isset($accountsData[$playerId][$corporationId])) {
                    $accountsData[$playerId][$corporationId] = [
                        'ids' => [],
                        'token' => null,
                    ];
                }
                $accountsData[$playerId][$corporationId]['ids'][] = $character->getId();
                if (
                    $accountsData[$playerId][$corporationId]['token'] === null &&
                    $character->getValidToken() &&
                    in_array('esi-corporations.read_corporation_membership.v1', $character->getScopesFromToken())
                ) {
                    $accountsData[$playerId][$corporationId]['token'] = $character->createAccessToken();
                }
            }
            if (count($accountsData[$playerId]) === 0) {
                unset($accountsData[$playerId]);
            }
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
            foreach ($corporations as $corporationId => $characters) {
                if ($characters['token'] === null) {
                    continue;
                }

                $this->checkErrorLimit();

                try {
                    $token = $this->tokenService->refreshAccessToken($characters['token']);
                } catch (IdentityProviderException $e) {
                    continue;
                }

                $members = $this->esiData->fetchCorporationMembers($corporationId, $token->getToken());

                if (
                    count($members) > 0 && // <0 is probably an error
                    count(array_diff($members, $characters['ids'])) === 0 // all members are on this account
                ) {
                    $whitelist[] = $corporationId;
                }

                usleep($this->sleep * 1000);
            }
        }

        return $whitelist;
    }

    private function saveWhitelist(\Neucore\Entity\Watchlist $watchlist, array $whitelist)
    {
        # TODO flag corp as auto added and remove those before adding the new list?

        foreach ($whitelist as $corporationId) {
            $corporation = $this->corporationRepository->find($corporationId);
            if ($corporation) {

                $watchlist->addWhitelistCorporation($corporation);
            }
        }

        if ($this->objectManager->flush()) {
            $this->writeLine('Success.', false);
        } else {
            $this->writeLine('Error.', false);
        }
    }
}
