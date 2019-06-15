<?php declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\PlayerRepository;
use Neucore\Service\AutoGroupAssignment;
use Neucore\Service\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatePlayerGroups extends Command
{
    use OutputTrait;

    /**
     * @var PlayerRepository
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
            ->setDescription('Assigns groups to players based on corporation configuration.');
        $this->configureOutputTrait($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->executeOutputTrait($input, $output);

        $this->writeln('Started "update-player-groups"', false);

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
                $this->writeln('  Error updating ' . $playerId);
            } else {
                $this->writeln('  Account ' . $playerId . ' groups updated');
            }
        }

        $this->writeln('Finished "update-player-groups"', false);
    }
}
