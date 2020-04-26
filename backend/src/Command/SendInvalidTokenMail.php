<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Command\Traits\EsiRateLimited;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\PlayerRepository;
use Neucore\Service\EveMail;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendInvalidTokenMail extends Command
{
    use LogOutput;
    use EsiRateLimited;

    /**
     * @var EveMail
     */
    private $eveMail;

    /**
     * @var PlayerRepository
     */
    private $playerRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var int
     */
    private $sleep;

    public function __construct(
        EveMail $eveMail,
        RepositoryFactory $repositoryFactory,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($repositoryFactory->getSystemVariableRepository());

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
                20
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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
                $offset
            ));
            $this->entityManager->clear(); // detaches all objects from Doctrine

            foreach ($playerIds as $playerId) {
                if (! $this->entityManager->isOpen()) {
                    $this->logger->critical('SendInvalidTokenMail: cannot continue without an open entity manager.');
                    break;
                }
                $this->checkErrorLimit();

                $characterId = $this->eveMail->invalidTokenFindCharacter($playerId);
                if ($characterId === null) {
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
                    strpos($errMessage, 'ContactCostNotApproved') !== false || // CSPA charge > 0
                    strpos($errMessage, 'ContactOwnerUnreachable') !== false // sender is blocked
                ) {
                    $this->eveMail->invalidTokenMailSent($playerId, true);
                    if ($errMessage === '') {
                        $this->writeLine('  Mail sent to ' . $characterId);
                    } else {
                        $this->writeLine(
                            "  Mail could not be sent to $characterId because of CSPA charge or blocked sender"
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
