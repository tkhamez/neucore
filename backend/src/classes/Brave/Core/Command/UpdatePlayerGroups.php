<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Entity\Player;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\AutoGroupAssignment;
use Brave\Core\Service\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
     * @var LoggerInterface
     */
    private $logger;

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
        AutoGroupAssignment $autoGroup,
        ObjectManager $objectManager,
        LoggerInterface $logger
    ) {
        parent::__construct();

        $this->playerRepo = $repositoryFactory->getPlayerRepository();
        $this->autoGroup = $autoGroup;
        $this->objectManager = $objectManager;
        $this->logger = $logger;
    }

    protected function configure()
    {
        $this->setName('update-player-groups')
            ->setDescription('Assigns groups to players based on corporation configuration.')
            ->addOption('log', 'l', InputOption::VALUE_NONE, 'Redirect output to log.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->log = (bool) $input->getOption('log');
        $this->output = $output;

        $this->writeln('* Started "update-player-groups"');

        $playerIds = [];
        $players = $this->playerRepo->findBy(['status' => Player::STATUS_STANDARD], ['lastUpdate' => 'ASC']);
        foreach ($players as $player) {
            $playerIds[] = $player->getId();
        }
        $this->objectManager->clear(); // detaches all objects from Doctrine

        foreach ($playerIds as $playerId) {
            $success1 = $this->autoGroup->assign($playerId);
            $success2 = $this->autoGroup->checkRequiredGroups($playerId);
            $this->objectManager->clear();
            if (! $success1 || ! $success2) {
                $this->writeln('Error updating ' . $playerId);
            } else {
                $this->writeln('Account ' . $playerId . ' groups updated');
            }
        }

        $this->writeln('* Finished "update-player-groups"');
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
