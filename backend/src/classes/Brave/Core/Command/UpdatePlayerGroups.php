<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Entity\PlayerRepository;
use Brave\Core\Service\AutoGroupAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class UpdatePlayerGroups extends Command
{
    /**
     * @var PlayerRepository
     */
    private $playerRepo;

    /**
     * @var AutoGroupAssignment
     */
    private $autoGroup;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(
        PlayerRepository $playerRepo,
        AutoGroupAssignment $autoGroup,
        EntityManagerInterface $em
    ) {
        parent::__construct();

        $this->playerRepo = $playerRepo;
        $this->autoGroup = $autoGroup;
        $this->em = $em;
    }

    protected function configure()
    {
        $this->setName('update-player-groups')
            ->setDescription('Assigns groups to players based on corporation configuration.')
            ->addOption('sleep', 's', InputOption::VALUE_OPTIONAL,
                'Time to sleep in milliseconds after each character update', 200);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sleep = (int) $input->getOption('sleep');

        $playerIds = [];
        $players = $this->playerRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($players as $player) {
            $playerIds[] = $player->getId();
        }
        $this->em->clear(); // detaches all objects from Doctrine

        foreach ($playerIds as $playerId) {
            $player = $this->autoGroup->assign($playerId);
            $this->em->clear();
            if ($player === null) {
                $output->writeln('Error updating ' . $playerId);
            } else {
                $output->writeln('Updated ' . $playerId);
            }
            usleep($sleep * 1000);
        }

        $output->writeln('All done.');
    }
}
