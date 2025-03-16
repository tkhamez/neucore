<?php

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\PlayerRepository;
use Neucore\Service\Account;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AssureMain extends Command
{
    private PlayerRepository $playerRepository;

    private EntityManagerInterface $entityManager;

    private Account $account;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        EntityManagerInterface $entityManager,
        Account $account,
    ) {
        parent::__construct();

        $this->playerRepository = $repositoryFactory->getPlayerRepository();
        $this->entityManager = $entityManager;
        $this->account = $account;
    }

    protected function configure(): void
    {
        $this
            ->setName('assure-main')
            ->setDescription('Makes sure that every player has a main character.')
            ->addOption('db-result-limit', null, InputOption::VALUE_OPTIONAL, '', '1000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Started "assure-main"');
        $dbResultLimit = intval($input->getOption('db-result-limit'));

        foreach ($this->getPlayerIds($dbResultLimit) as $i => $playerIds) {
            $this->entityManager->clear(); // detaches all objects from Doctrine
            foreach ($playerIds as $j => $playerId) {
                if (!$this->entityManager->isOpen()) {
                    $output->writeln('AssureMain: cannot continue without an open entity manager.');
                    break;
                }

                $player = $this->playerRepository->find($playerId);
                if ($player) {
                    $this->account->assureMain($player);
                }

                if ($j % 100 === 0) { // reduce memory usage
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                    $output->writeln("  processed " . ($j + 1) . " in iteration " . ($i + 1));
                }
            }
            $this->entityManager->flush();
        }

        $output->writeln('Finished "assure-main"');

        return 0;
    }

    private function getPlayerIds(int $dbResultLimit): iterable
    {
        $offset = $dbResultLimit * -1;
        do {
            $offset += $dbResultLimit;
            $playerIds = array_map(function (Player $player) {
                return $player->getId();
            }, $this->playerRepository->findBy([], [], $dbResultLimit, $offset));

            yield $playerIds;
        } while (count($playerIds) === $dbResultLimit);
    }
}
