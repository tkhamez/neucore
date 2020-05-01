<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\PlayerRepository;
use Neucore\Service\Account;
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
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var Account
     */
    private $account;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        Account $account
    ) {
        parent::__construct();
        $this->logOutput($logger);

        $this->playerRepo = $repositoryFactory->getPlayerRepository();
        $this->entityManager = $entityManager;
        $this->account = $account;
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
                30
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

            foreach ($playerIds as $i => $playerId) {
                if (! $this->entityManager->isOpen()) {
                    $this->logger->critical('UpdatePlayerGroups: cannot continue without an open entity manager.');
                    break;
                }
                $success = $this->account->updateGroups($playerId);
                if (! $success) {
                    $this->writeLine('  Error updating ' . $playerId);
                } else {
                    $this->writeLine('  Account ' . $playerId . ' groups updated');
                }

                if ($i % 100 === 0) { // reduce memory usage
                    $this->entityManager->clear();
                }
                usleep($sleep * 1000);
            }
        } while (count($playerIds) === $dbResultLimit);

        $this->writeLine('Finished "update-player-groups"', false);

        return 0;
    }
}
