<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Command\Traits\EsiLimits;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Character;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterRepository;
use Neucore\Service\Character as CharacterService;
use Neucore\Service\EntityManager;
use Neucore\Service\EsiData;
use Neucore\Storage\StorageDatabaseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCharacters extends Command
{
    use LogOutput;
    use EsiLimits;

    private CharacterRepository $charRepo;

    private EsiData $esiData;

    private CharacterService $characterService;

    private EntityManager $entityManager;

    private int $sleep = 5;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        EsiData $esiData,
        CharacterService $characterService,
        EntityManager $entityManager,
        LoggerInterface $logger,
        StorageDatabaseInterface $storage,
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiLimits($storage, $logger);

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->esiData = $esiData;
        $this->characterService = $characterService;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('update-chars')
            ->setDescription('Updates all characters from ESI.')
            ->addArgument('character', InputArgument::OPTIONAL, 'Update one char.')
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
        $charId = intval($input->getArgument('character'));
        $this->sleep = intval($input->getOption('sleep'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('Started "update-chars"', false);

        $this->updateChars($charId);

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

            $this->checkLimits();

            $names = [];
            foreach ($this->esiData->fetchUniverseNames($charIds) as $name) {
                /** @noinspection PhpCastIsUnnecessaryInspection */
                $names[$name->getId()] = (string) $name->getName();
            }

            $affiliations = [];
            foreach ($this->esiData->fetchCharactersAffiliation($charIds) as $affiliation) {
                $affiliations[$affiliation->getCharacterId()] = [
                    'corporation' => $affiliation->getCorporationId(),
                    'alliance' => $affiliation->getAllianceId(),
                ];
            }

            $updateOk = [];
            foreach ($characters as $char) {
                if (!$this->entityManager->isOpen()) {
                    $this->logger->critical('UpdateCharacters: cannot continue without an open entity manager.');
                    break;
                }

                if (!isset($affiliations[$char->getId()])) {
                    $this->writeLine('  Character ' . $char->getId() . ': update NOK');
                    continue;
                }

                if (isset($names[$char->getId()])) {
                    $this->characterService->setCharacterName($char, $names[$char->getId()]);
                    if ($char->getMain()) {
                        $char->getPlayer()->setName($char->getName());
                    }
                }

                $corp = $this->esiData->getCorporationEntity($affiliations[$char->getId()]['corporation']);
                $char->setCorporation($corp);
                $corp->addCharacter($char);

                try {
                    $char->setLastUpdate(new \DateTime());
                } catch (\Exception) {
                    // ignore
                }

                $updateOk[] = $char->getId();
                usleep($this->sleep * 1000); // reduce CPU usage
            }
            if (!empty($updateOk) && $this->entityManager->flush2()) {
                $this->writeLine('  Characters ' . implode(',', $updateOk) . ': update OK');
            }

            $this->entityManager->clear(); // detaches all objects from Doctrine
        } while (count($charIds) === $loopLimit);
    }
}
