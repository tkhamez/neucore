<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Service\CharacterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class UpdateCharacters extends Command
{
    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var CharacterService
     */
    private $charService;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(
        CharacterRepository $charRepo,
        CharacterService $charService,
        EntityManagerInterface $em
    ) {
        parent::__construct();

        $this->charRepo = $charRepo;
        $this->charService = $charService;
        $this->em = $em;
    }

    protected function configure()
    {
        $this->setName('update-chars')
            ->setDescription('Updates all characters from ESI.')
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
        $this->em->clear(); // detaches all objects from Doctrine

        foreach ($charIds as $charId) {
            $char = $this->charService->fetchCharacter($charId, true);
            $this->em->clear();
            if ($char === null) {
                $output->writeln('Error updating ' . $charId);
            } else {
                $output->writeln('Updated ' . $charId);
            }
            usleep($sleep * 1000);
        }

        $output->writeln('All done.');
    }
}
