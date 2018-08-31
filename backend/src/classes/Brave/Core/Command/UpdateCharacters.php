<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Repository\RepositoryFactory;
use Brave\Core\Service\CoreCharacter;
use Brave\Core\Service\EsiCharacter;
use Brave\Core\Service\ObjectManager;
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
     * @var EsiCharacter
     */
    private $esiCharService;

    /**
     * @var CoreCharacter
     */
    private $coreCharService;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var int
     */
    private $sleep;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        EsiCharacter $esiCharService,
        CoreCharacter $coreCharService,
        ObjectManager $objectManager
    ) {
        parent::__construct();

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->alliRepo = $repositoryFactory->getAllianceRepository();
        $this->esiCharService = $esiCharService;
        $this->coreCharService = $coreCharService;
        $this->objectManager = $objectManager;
    }

    protected function configure()
    {
        $this->setName('update-chars')
            ->setDescription('Updates all characters, corporations and alliances from ESI and checks refresh token.')
            ->addOption('sleep', 's', InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each character update', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->sleep = (int) $input->getOption('sleep');

        $this->updateChars($output);
        $this->updateCorps($output);
        $this->updateAlliances($output);

        $output->writeln('All done.');
    }

    private function updateChars(OutputInterface $output)
    {
        $charIds = [];
        $chars = $this->charRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($chars as $char) {
            $charIds[] = $char->getId();
        }

        foreach ($charIds as $charId) {
            $this->objectManager->clear(); // detaches all objects from Doctrine
            usleep($this->sleep * 1000);

            // update name, corp and alliance from ESI
            $updatedChar = $this->esiCharService->fetchCharacter($charId);
            if ($updatedChar === null) {
                $output->writeln('Character ' . $charId.': error updating.');
                continue;
            }

            // check refresh token, update character owner hash
            if ((string) $updatedChar->getRefreshToken() === '') {
                $output->writeln('Character ' . $charId.': update OK, token N/A');
                continue;
            }
            if ($this->coreCharService->checkTokenUpdateCharacter($updatedChar)) {
                $output->writeln('Character ' . $charId.': update OK, token OK');
            } else {
                $output->writeln('Character ' . $charId.': update OK, token NOK');
            }
        }
    }

    private function updateCorps(OutputInterface $output)
    {
        $corpIds = [];
        $corps = $this->corpRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($corps as $corp) {
            $corpIds[] = $corp->getId();
        }

        foreach ($corpIds as $corpId) {
            $this->objectManager->clear();
            usleep($this->sleep * 1000);

            $updatedCorp = $this->esiCharService->fetchCorporation($corpId);
            if ($updatedCorp === null) {
                $output->writeln('Corporation ' . $corpId.': update NOK');
                continue;
            } else {
                $output->writeln('Corporation ' . $corpId.': update OK');
            }
        }
    }

    private function updateAlliances(OutputInterface $output)
    {
        $alliIds = [];
        $allis = $this->alliRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($allis as $alli) {
            $alliIds[] = $alli->getId();
        }

        foreach ($alliIds as $alliId) {
            $this->objectManager->clear();
            usleep($this->sleep * 1000);

            $updatedAlli = $this->esiCharService->fetchAlliance($alliId);
            if ($updatedAlli === null) {
                $output->writeln('Alliance ' . $alliId.': update NOK');
                continue;
            } else {
                $output->writeln('Alliance ' . $alliId.': update OK');
            }
        }
    }
}
