<?php declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\PlayerRepository;
use Neucore\Service\AutoGroupAssignment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdatePlayerGroups extends Command
{
    use LogOutput;

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
    private $entityManager;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        AutoGroupAssignment $autoGroup,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->logOutput($logger);

        $this->playerRepo = $repositoryFactory->getPlayerRepository();
        $this->autoGroup = $autoGroup;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('update-player-groups')
            ->setDescription('Assigns groups to players based on corporation configuration.')
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
        $this->executeLogOutput($input, $output);
        $sleep = intval($input->getOption('sleep'));

        $this->writeLine('Started "update-player-groups"', false);

        $dbResultLimit = 1000;
        $offset = $dbResultLimit * -1;
        do {
            $offset += $dbResultLimit;
            $playerIds = array_map(function (Player $player) {
                return $player->getId();
            }, $this->playerRepo->findBy(
                ['status' => Player::STATUS_STANDARD],
                ['lastUpdate' => 'ASC'],
                $dbResultLimit,
                $offset
            ));

            $this->entityManager->clear(); // detaches all objects from Doctrine

            foreach ($playerIds as $playerId) {
                if (! $this->entityManager->isOpen()) {
                    $this->logger->critical('UpdatePlayerGroups: cannot continue without an open entity manager.');
                    break;
                }
                $success1 = $this->autoGroup->assign($playerId);
                $success2 = $this->autoGroup->checkRequiredGroups($playerId);
                $this->entityManager->clear();
                if (! $success1 || ! $success2) {
                    $this->writeLine('  Error updating ' . $playerId);
                } else {
                    $this->writeLine('  Account ' . $playerId . ' groups updated');
                }

                usleep($sleep * 1000);
            }
        } while (count($playerIds) === $dbResultLimit);

        $this->writeLine('Finished "update-player-groups"', false);

        return 0;
    }
}
