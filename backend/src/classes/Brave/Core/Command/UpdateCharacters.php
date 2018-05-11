<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Service\CoreCharacter;
use Brave\Core\Service\EsiCharacter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCharacters extends Command
{
    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var EsiCharacter
     */
    private $esiCharService;

    /**
     * @var CoreCharacter
     */
    private $coreCharService;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var LoggerInterface
     */
    private $log;

    public function __construct(
        CharacterRepository $charRepo,
        EsiCharacter $esiCharService,
        CoreCharacter $coreCharService,
        EntityManagerInterface $em,
        LoggerInterface $log
    ) {
        parent::__construct();

        $this->charRepo = $charRepo;
        $this->esiCharService = $esiCharService;
        $this->coreCharService = $coreCharService;
        $this->em = $em;
        $this->log = $log;
    }

    protected function configure()
    {
        $this->setName('update-chars')
            ->setDescription('Updates all characters from ESI and checks refresh token.')
            ->addOption('sleep', 's', InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each character update', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sleep = (int) $input->getOption('sleep');

        $charIds = [];
        $chars = $this->charRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($chars as $char) {
            $charIds[] = $char->getId();
        }

        foreach ($charIds as $charId) {
            $this->em->clear(); // detaches all objects from Doctrine
            usleep($sleep * 1000);

            // update name, corp and alliance from ESI
            $updatedChar = $this->esiCharService->fetchCharacter($charId, true);
            if ($updatedChar === null) {
                $output->writeln($charId.': error updating.');
                continue;
            }

            // check refresh token, update character owner hash
            if ((string) $updatedChar->getRefreshToken() === '') {
                $output->writeln($charId.': update OK, token N/A');
                continue;
            }
            if ($this->coreCharService->checkTokenUpdateCharacter($updatedChar)) {
                $output->writeln($charId.': update OK, token OK');
            } else {
                $output->writeln($charId.': update OK, token NOK');
            }
        }

        $output->writeln('All done.');
    }
}
