<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\CharacterService;
use Brave\Core\Service\EsiData;
use Brave\Core\Service\OAuthToken;
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
     * @var EsiData
     */
    private $esiData;

    /**
     * @var CharacterService
     */
    private $charService;

    /**
     * @var OAuthToken
     */
    private $tokenService;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var int
     */
    private $sleep;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        EsiData $esiData,
        CharacterService $charService,
        OAuthToken $tokenService,
        ObjectManager $objectManager
    ) {
        parent::__construct();

        $this->charRepo = $repositoryFactory->getCharacterRepository();
        $this->corpRepo = $repositoryFactory->getCorporationRepository();
        $this->alliRepo = $repositoryFactory->getAllianceRepository();
        $this->esiData = $esiData;
        $this->charService = $charService;
        $this->tokenService = $tokenService;
        $this->objectManager = $objectManager;
    }

    protected function configure()
    {
        $this->setName('update-chars')
            ->setDescription(
                'Updates all characters, corporations and alliances from ESI and checks refresh token. ' .
                'If the character owner hash has changed, that character will be deleted.'
            )
            ->addOption('sleep', 's', InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each update', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->sleep = (int) $input->getOption('sleep');
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
                $this->writeln('Character ' . $charId.': error updating.');
                continue;
            }

            // check token and character owner hash - this may delete the character!
            $result = $this->charService->checkAndUpdateCharacter($updatedChar, $this->tokenService);
            if ($result === CharacterService::CHECK_TOKEN_OK) {
                $this->writeln('Character ' . $charId.': update OK, token OK');
            } elseif ($result === CharacterService::CHECK_TOKEN_NOK) {
                $this->writeln('Character ' . $charId.': update OK, token NOK');
            } elseif ($result === CharacterService::CHECK_CHAR_DELETED) {
                $this->writeln('Character ' . $charId.': update OK, character deleted');
            } else {
                $this->writeln('Character ' . $charId.': unknown result');
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
                continue;
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
                continue;
            } else {
                $this->writeln('Alliance ' . $alliId.': update OK');
            }

            usleep($this->sleep * 1000);
        }
    }

    private function writeln($text)
    {
        $this->output->writeln(date('Y-m-d H:i:s ') . $text);
    }
}
