<?php declare(strict_types=1);

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
use Neucore\Service\EsiData;
use Neucore\Service\ObjectManager;
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
     * @var ObjectManager
     */
    private $objectManager;

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
        ObjectManager $objectManager,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($repositoryFactory->getSystemVariableRepository());

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->alliRepo = $repositoryFactory->getAllianceRepository();
        $this->esiData = $esiData;
        $this->objectManager = $objectManager;
    }

    protected function configure()
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
    }

    private function updateChars($characterId = 0)
    {
        $offset = $this->dbResultLimit * -1;
        do {
            if ($characterId !== 0) {
                $charIds = [$characterId];
            } else {
                $offset += $this->dbResultLimit;
                $charIds = array_map(function (Character $char) {
                    return $char->getId();
                }, $this->charRepo->findBy([], ['lastUpdate' => 'ASC'], $this->dbResultLimit, $offset));
            }

            foreach ($charIds as $charId) {
                if (! $this->objectManager->isOpen()) {
                    $this->logger->critical('UpdateCharacters: cannot continue without an open entity manager.');
                    break;
                }
                $this->objectManager->clear(); // detaches all objects from Doctrine
                $this->checkErrorLimit();

                // update name, corp and alliance from ESI
                $updatedChar = $this->esiData->fetchCharacter($charId);
                if ($updatedChar === null) {
                    $this->writeLine('  Character ' . $charId.': ' . self::UPDATE_NOK);
                } else {
                    $this->writeLine('  Character ' . $charId.': ' . self::UPDATE_OK);
                }

                usleep($this->sleep * 1000);
            }
        } while (count($charIds) === $this->dbResultLimit);
    }

    private function updateCorps()
    {
        $offset = $this->dbResultLimit * -1;
        do {
            $offset += $this->dbResultLimit;
            $corpIds = array_map(function (Corporation $corp) {
                return $corp->getId();
            }, $this->corpRepo->findBy([], ['lastUpdate' => 'ASC'], $this->dbResultLimit, $offset));

            foreach ($corpIds as $corpId) {
                if (! $this->objectManager->isOpen()) {
                    $this->logger->critical('UpdateCharacters: cannot continue without an open entity manager.');
                    break;
                }
                $this->objectManager->clear();
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

    private function updateAlliances()
    {
        $alliIds = array_map(function (Alliance $alli) {
            return $alli->getId();
        }, $this->alliRepo->findBy([], ['lastUpdate' => 'ASC']));

        foreach ($alliIds as $alliId) {
            if (! $this->objectManager->isOpen()) {
                $this->logger->critical('UpdateCharacters: cannot continue without an open entity manager.');
                break;
            }
            $this->objectManager->clear();
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
