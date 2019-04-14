<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\EsiData;
use Brave\Core\Service\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCharacters extends Command
{
    /**
     * @var \Brave\Core\Repository\CharacterRepository
     */
    private $charRepo;

    /**
     * @var \Brave\Core\Repository\CorporationRepository
     */
    private $corpRepo;

    /**
     * @var \Brave\Core\Repository\AllianceRepository
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var int
     */
    private $sleep;

    /**
     * @var bool
     */
    private $log;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        EsiData $esiData,
        ObjectManager $objectManager,
        LoggerInterface $logger
    ) {
        parent::__construct();

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->alliRepo = $repositoryFactory->getAllianceRepository();
        $this->esiData = $esiData;
        $this->objectManager = $objectManager;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setName('update-chars')
            ->setDescription('Updates all characters, corporations and alliances from ESI.')
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each update',
                200
            )
            ->addOption('log', 'l', InputOption::VALUE_NONE, 'Redirect output to log.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->sleep = (int) $input->getOption('sleep');
        $this->log = $input->getOption('log');
        $this->output = $output;

        $this->writeln('* Started "update-chars"');

        $this->updateChars();
        $this->updateCorps();
        $this->updateAlliances();

        $this->writeln('* Finished "update-chars"');
    }

    private function updateChars()
    {
        $charIds = [];
        $chars = $this->charRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($chars as $char) {
            $charIds[] = $char->getId();
        }

        foreach ($charIds as $charId) {
            $this->objectManager->clear(); // detaches all objects from Doctrine

            // update name, corp and alliance from ESI
            $updatedChar = $this->esiData->fetchCharacter($charId);
            if ($updatedChar === null) {
                $this->writeln('Character ' . $charId.': update NOK');
            } else {
                $this->writeln('Character ' . $charId.': update OK');
            }

            usleep($this->sleep * 1000);
        }
    }

    private function updateCorps()
    {
        $corpIds = [];
        $corps = $this->corpRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($corps as $corp) {
            $corpIds[] = $corp->getId();
        }

        foreach ($corpIds as $corpId) {
            $this->objectManager->clear();

            $updatedCorp = $this->esiData->fetchCorporation($corpId);
            if ($updatedCorp === null) {
                $this->writeln('Corporation ' . $corpId.': update NOK');
            } else {
                $this->writeln('Corporation ' . $corpId.': update OK');
            }

            usleep($this->sleep * 1000);
        }
    }

    private function updateAlliances()
    {
        $alliIds = [];
        $allis = $this->alliRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($allis as $alli) {
            $alliIds[] = $alli->getId();
        }

        foreach ($alliIds as $alliId) {
            $this->objectManager->clear();

            $updatedAlli = $this->esiData->fetchAlliance($alliId);
            if ($updatedAlli === null) {
                $this->writeln('Alliance ' . $alliId.': update NOK');
            } else {
                $this->writeln('Alliance ' . $alliId.': update OK');
            }

            usleep($this->sleep * 1000);
        }
    }

    private function writeln($text)
    {
        if ($this->log) {
            $this->logger->info($text);
        } else {
            $this->output->writeln(date('Y-m-d H:i:s ') . $text);
        }
    }
}
