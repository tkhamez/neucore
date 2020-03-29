<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Command\Traits\EsiRateLimited;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\AllianceRepository;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\CorporationRepository;
use Neucore\Service\EntityManager;
use Neucore\Service\EsiData;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCharacters extends Command
{
    use LogOutput;
    use EsiRateLimited;

    const UPDATE_OK = 'update OK';

    const UPDATE_NOK = 'update NOK';

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var CorporationRepository
     */
    private $corpRepo;

    /**
     * @var AllianceRepository
     */
    private $alliRepo;

    /**
     * @var EsiData
     */
    private $esiData;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var int
     */
    private $sleep;

    /**
     * @var int
     */
    private $dbResultLimit = 1000;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        EsiData $esiData,
        EntityManager $entityManager,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($repositoryFactory->getSystemVariableRepository());

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->alliRepo = $repositoryFactory->getAllianceRepository();
        $this->esiData = $esiData;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('update-chars')
            ->setDescription('Updates all characters, corporations and alliances from ESI.')
            ->addArgument('character', InputArgument::OPTIONAL, 'Update one char, no corporations or alliances.')
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each update',
                50
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $charId = intval($input->getArgument('character'));
        $this->sleep = intval($input->getOption('sleep'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('Started "update-chars"', false);

        $this->updateChars($charId);
        if ($charId === 0) {
            $this->updateCorps();
            $this->updateAlliances();
        }

        $this->writeLine('Finished "update-chars"', false);

        return 0;
    }

    private function updateChars(int $characterId = 0): void
    {
        $loopLimit = 400; // reduce memory usage
        $offset = $loopLimit * -1;
        do {
            $characters = [];
            $charIds = [];
            if ($characterId !== 0) {
                if (($character = $this->charRepo->find($characterId)) !== null) {
                    $characters = [$character];
                    $charIds = [$characterId];
                }
            } else {
                $offset += $loopLimit;
                $characters = $this->charRepo->findBy([], ['lastUpdate' => 'ASC'], $loopLimit, $offset);
                $charIds = array_map(function (Character $char) {
                    return $char->getId();
                }, $characters);
            }

            $this->checkErrorLimit();

            $names = [];
            foreach ($this->esiData->fetchUniverseNames($charIds) as $name) {
                $names[$name->getId()] = $name->getName();
            }

            $affiliations = [];
            foreach ($this->esiData->fetchCharactersAffiliation($charIds) as $affiliation) {
                $affiliations[$affiliation->getCharacterId()] = [
                    'corporation' => $affiliation->getCorporationId(),
                    'alliance' => $affiliation->getAllianceId()
                ];
            }

            $updateOk = [];
            foreach ($characters as $idx => $char) {
                if (! $this->entityManager->isOpen()) {
                    $this->logger->critical('UpdateCharacters: cannot continue without an open entity manager.');
                    break;
                }

                if (! isset($names[$char->getId()]) || ! isset($affiliations[$char->getId()])) {
                    $this->writeLine('  Character ' . $char->getId().': ' . self::UPDATE_NOK);
                    continue;
                }

                $char->setName($names[$char->getId()]);
                if ($char->getMain()) {
                    $char->getPlayer()->setName($char->getName());
                }

                $corp = $this->esiData->getCorporationEntity($affiliations[$char->getId()]['corporation']);
                $char->setCorporation($corp);
                $corp->addCharacter($char);

                try {
                    $char->setLastUpdate(new \DateTime());
                } catch (\Exception $e) {
                    // ignore
                }

                $updateOk[] = $char->getId();
                if ($idx % 4 === 0) {
                    usleep($this->sleep * 1000); // reduce CPU usage
                }
            }
            if (count($updateOk) > 0 && $this->entityManager->flush()) {
                $this->writeLine('  Characters ' . implode(',', $updateOk).': ' . self::UPDATE_OK);
            }

            $this->entityManager->clear(); // detaches all objects from Doctrine
        } while (count($charIds) === $loopLimit);
    }

    private function updateCorps(): void
    {
        $offset = $this->dbResultLimit * -1;
        do {
            $offset += $this->dbResultLimit;
            $corpIds = array_map(function (Corporation $corp) {
                return $corp->getId();
            }, $this->corpRepo->findBy([], ['lastUpdate' => 'ASC'], $this->dbResultLimit, $offset));

            foreach ($corpIds as $corpId) {
                if (! $this->entityManager->isOpen()) {
                    $this->logger->critical('UpdateCharacters: cannot continue without an open entity manager.');
                    break;
                }
                $this->entityManager->clear();
                $this->checkErrorLimit();

                $updatedCorp = $this->esiData->fetchCorporation($corpId);
                if ($updatedCorp === null) {
                    $this->writeLine('  Corporation ' . $corpId.': ' . self::UPDATE_NOK);
                } else {
                    $this->writeLine('  Corporation ' . $corpId.': ' . self::UPDATE_OK);
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
            if (! $this->entityManager->isOpen()) {
                $this->logger->critical('UpdateCharacters: cannot continue without an open entity manager.');
                break;
            }
            $this->entityManager->clear();
            $this->checkErrorLimit();

            $updatedAlli = $this->esiData->fetchAlliance($alliId);
            if ($updatedAlli === null) {
                $this->writeLine('  Alliance ' . $alliId.': ' . self::UPDATE_NOK);
            } else {
                $this->writeLine('  Alliance ' . $alliId.': ' . self::UPDATE_OK);
            }

            usleep($this->sleep * 1000);
        }
    }
}
