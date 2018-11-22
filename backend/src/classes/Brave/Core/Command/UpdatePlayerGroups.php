<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\AutoGroupAssignment;
use Brave\Core\Service\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatePlayerGroups extends Command
{
    /**
     * @var \Brave\Core\Repository\PlayerRepository
     */
    private $playerRepo;

    /**
     * @var AutoGroupAssignment
     */
    private $autoGroup;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var OutputInterface
     */
    private $output;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        AutoGroupAssignment $autoGroup,
        ObjectManager $objectManager
    ) {
        parent::__construct();

        $this->playerRepo = $repositoryFactory->getPlayerRepository();
        $this->autoGroup = $autoGroup;
        $this->objectManager = $objectManager;
    }

    protected function configure()
    {
        $this->setName('update-player-groups')
            ->setDescription('Assigns groups to players based on corporation configuration.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $this->writeln('update-player-groups: starting.');

        $playerIds = [];
        $players = $this->playerRepo->findBy([], ['lastUpdate' => 'ASC']);
        foreach ($players as $player) {
            $playerIds[] = $player->getId();
        }
        $this->objectManager->clear(); // detaches all objects from Doctrine

        foreach ($playerIds as $playerId) {
            $player = $this->autoGroup->assign($playerId);
            $this->objectManager->clear();
            if ($player === null) {
                $this->writeln('Error updating ' . $playerId);
            } else {
                $this->writeln('Account ' . $playerId . ' groups updated');
            }
        }

        $this->writeln('update-player-groups: finished.');
    }

    private function writeln($text)
    {
        $this->output->writeln(date('Y-m-d H:i:s ') . $text);
    }
}
