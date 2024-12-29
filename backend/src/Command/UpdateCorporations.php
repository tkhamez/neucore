<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Command\Traits\EsiRateLimited;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\AllianceRepository;
use Neucore\Repository\CorporationRepository;
use Neucore\Service\EntityManager;
use Neucore\Service\EsiData;
use Neucore\Storage\StorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCorporations extends Command
{
    use LogOutput;
    use EsiRateLimited;

    public const UPDATE_OK = 'update OK';

    public const UPDATE_NOK = 'update NOK';

    private CorporationRepository $corpRepo;

    private AllianceRepository $alliRepo;

    private EsiData $esiData;

    private EntityManager $entityManager;

    private int $sleep = 50;

    private int $dbResultLimit = 1000;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        EsiData $esiData,
        EntityManager $entityManager,
        LoggerInterface $logger,
        StorageInterface $storage,
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($storage, $logger);

        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->alliRepo = $repositoryFactory->getAllianceRepository();
        $this->esiData = $esiData;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('update-corporations')
            ->setDescription('Updates all corporations and alliances from ESI.')
            ->addArgument('corporation', InputArgument::OPTIONAL, 'Update one corporation, no alliances.')
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each update',
                $this->sleep,
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $corpId = intval($input->getArgument('corporation'));
        $this->sleep = intval($input->getOption('sleep'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('Started "update-corporations"', false);

        $this->updateCorps($corpId);
        if ($corpId === 0) {
            $this->updateAlliances();
        }

        $this->writeLine('Finished "update-corporations"', false);

        return 0;
    }

    private function updateCorps(int $corporationId = 0): void
    {
        $offset = $this->dbResultLimit * -1;
        do {
            $offset += $this->dbResultLimit;
            if ($corporationId !== 0) {
                $corpIds = [$corporationId];
            } else {
                $corpIds = array_map(function (Corporation $corp) {
                    return $corp->getId();
                }, $this->corpRepo->findBy([], ['lastUpdate' => 'ASC'], $this->dbResultLimit, $offset));
            }

            foreach ($corpIds as $corpId) {
                if (!$this->entityManager->isOpen()) {
                    $this->logger->critical('UpdateCharacters: cannot continue without an open entity manager.');
                    break;
                }
                $this->entityManager->clear();
                $this->checkForErrors();

                $updatedCorp = $this->esiData->fetchCorporation($corpId);
                if ($updatedCorp === null) {
                    $this->writeLine('  Corporation ' . $corpId . ': ' . self::UPDATE_NOK);
                } else {
                    $this->writeLine('  Corporation ' . $corpId . ': ' . self::UPDATE_OK);
                }

                usleep($this->sleep * 1000);
            }
        } while (count($corpIds) === $this->dbResultLimit);
    }

    private function updateAlliances(): void
    {
        $alliIds = array_map(function (Alliance $alli) {
            return $alli->getId();
        }, $this->alliRepo->findBy([], ['lastUpdate' => 'ASC']));

        foreach ($alliIds as $alliId) {
            if (!$this->entityManager->isOpen()) {
                $this->logger->critical('UpdateCharacters: cannot continue without an open entity manager.');
                break;
            }
            $this->entityManager->clear();
            $this->checkForErrors();

            $updatedAlli = $this->esiData->fetchAlliance($alliId);
            if ($updatedAlli === null) {
                $this->writeLine('  Alliance ' . $alliId . ': ' . self::UPDATE_NOK);
            } else {
                $this->writeLine('  Alliance ' . $alliId . ': ' . self::UPDATE_OK);
            }

            usleep($this->sleep * 1000);
        }
    }
}
