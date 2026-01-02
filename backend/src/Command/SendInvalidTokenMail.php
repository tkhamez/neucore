<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Command\Traits\EsiLimits;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\PlayerRepository;
use Neucore\Service\EveMail;
use Neucore\Storage\StorageDatabaseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendInvalidTokenMail extends Command
{
    use LogOutput;
    use EsiLimits;

    private EveMail $eveMail;

    private PlayerRepository $playerRepository;

    private EntityManagerInterface $entityManager;

    private int $sleep = 20;

    public function __construct(
        EveMail $eveMail,
        RepositoryFactory $repositoryFactory,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        StorageDatabaseInterface $storage,
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiLimits($storage, $logger);

        $this->eveMail = $eveMail;
        $this->playerRepository = $repositoryFactory->getPlayerRepository();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('send-invalid-token-mail')
            ->setDescription('Sends "invalid ESI token" EVE mail notification.')
            ->addOption(
                'sleep',
                's',
                InputOption::VALUE_OPTIONAL,
                'Time to sleep in seconds after each mail sent (ESI rate limit is 4/min)',
                $this->sleep,
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->sleep = intval($input->getOption('sleep'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('Started "send-invalid-token-mail"', false);
        $this->send();
        $this->writeLine('Finished "send-invalid-token-mail"', false);

        return 0;
    }

    private function send(): void
    {
        $notActiveReason = $this->eveMail->invalidTokenIsActive();
        if ($notActiveReason !== '') {
            $this->writeLine(' ' . $notActiveReason, false);
            return;
        }

        $dbResultLimit = 1000;
        $offset = $dbResultLimit * -1;
        do {
            $offset += $dbResultLimit;
            $playerIds = array_map(function (Player $player) {
                return $player->getId();
            }, $this->playerRepository->findBy(
                ['status' => Player::STATUS_STANDARD],
                ['lastUpdate' => 'ASC'],
                $dbResultLimit,
                $offset,
            ));
            $this->entityManager->clear(); // detaches all objects from Doctrine

            foreach ($playerIds as $playerId) {
                if (!$this->entityManager->isOpen()) {
                    $this->logger->critical('SendInvalidTokenMail: cannot continue without an open entity manager.');
                    break;
                }
                $this->checkLimits();

                $characterId = $this->eveMail->invalidTokenFindCharacter($playerId);
                if ($characterId === null) {
                    // The status should already be correct, just in case.
                    $this->eveMail->invalidTokenMailSent($playerId, false);
                    continue;
                }

                $mayNotSendReason = $this->eveMail->invalidTokenMaySend($characterId);
                if ($mayNotSendReason !== '') {
                    continue;
                }

                $errMessage = $this->eveMail->invalidTokenSend($characterId);
                if (
                    $errMessage === '' || // success
                    str_contains($errMessage, 'ContactCostNotApproved') || // CSPA charge > 0
                    str_contains($errMessage, 'ContactOwnerUnreachable') // sender is blocked
                ) {
                    $this->eveMail->invalidTokenMailSent($playerId, true);
                    if ($errMessage === '') {
                        $this->writeLine('  Invalid token mail sent to ' . $characterId, false);
                    } else {
                        $this->writeLine(
                            "  Invalid token mail could not be sent to $characterId " .
                                "because of CSPA charge or blocked sender",
                            false,
                        );
                    }
                    usleep($this->sleep * 1000 * 1000);
                } else {
                    $this->writeLine(' ' . $errMessage, false);
                }
            }
        } while (count($playerIds) === $dbResultLimit);
    }
}
