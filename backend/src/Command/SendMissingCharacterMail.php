<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Api;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\SystemVariable;
use Neucore\Repository\CorporationMemberRepository;
use Neucore\Repository\SystemVariableRepository;
use Neucore\Command\Traits\EsiLimits;
use Neucore\Command\Traits\LogOutput;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EveMail;
use Neucore\Storage\StorageDatabaseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendMissingCharacterMail extends Command
{
    use LogOutput;
    use EsiLimits;

    private EveMail $eveMail;

    private CorporationMemberRepository $corporationMemberRepository;

    private SystemVariableRepository $sysVarRepository;

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
        $this->corporationMemberRepository = $repositoryFactory->getCorporationMemberRepository();
        $this->sysVarRepository = $repositoryFactory->getSystemVariableRepository();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setName('send-missing-character-mail')
            ->setDescription('Sends "missing character" EVE mail notification.')
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

        $this->writeLine('Started "send-missing-character-mail"', false);
        $this->send();
        $this->writeLine('Finished "send-missing-character-mail"', false);

        return 0;
    }

    private function send(): void
    {
        $notActiveReason = $this->eveMail->missingCharacterIsActive();
        if ($notActiveReason !== '') {
            $this->writeLine(' ' . $notActiveReason, false);
            return;
        }

        // read config
        $daysVar = $this->sysVarRepository->find(SystemVariable::MAIL_MISSING_CHARACTER_RESEND);
        if (!$daysVar || (int) $daysVar->getValue() <= 0) {
            $this->writeLine(' Invalid config.', false);
            return;
        }
        $days = (int) $daysVar->getValue();

        $dbResultLimit = 1000;
        $offset = $dbResultLimit * -1;
        do {
            $offset += $dbResultLimit;
            $memberIds = array_map(function (CorporationMember $member) {
                return $member->getId();
            }, $this->corporationMemberRepository->findByCorporationsWithoutAccountAndActive(
                $this->eveMail->missingCharacterGetCorporations(),
                $days,
                $dbResultLimit,
                $offset,
            ));
            $this->entityManager->clear(); // detaches all objects from Doctrine

            foreach ($memberIds as $memberId) {
                if (!$this->entityManager->isOpen()) {
                    $this->logger->critical('SendInvalidTokenMail: cannot continue without an open entity manager.');
                    break;
                }
                $this->checkLimits();

                $mayNotSendReason = $this->eveMail->missingCharacterMaySend($memberId);
                if ($mayNotSendReason !== '') {
                    continue;
                }

                $errorMessage = $this->eveMail->missingCharacterSend($memberId);
                $result = null;
                if ($errorMessage == '') { // success
                    $result = Api::MAIL_OK;
                } elseif (str_contains($errorMessage, 'ContactCostNotApproved')) {
                    $result = Api::MAIL_ERROR_CSPA;
                } elseif (str_contains($errorMessage, 'ContactOwnerUnreachable')) {
                    $result = Api::MAIL_ERROR_BLOCKED;
                }
                if ($result !== null) {
                    $this->eveMail->missingCharacterMailSent($memberId, $result);
                    if ($errorMessage === '') {
                        $this->writeLine('  Missing character mail sent to ' . $memberId, false);
                    } else {
                        $this->writeLine(
                            "  Missing character mail could not be sent to $memberId " .
                                "because of CSPA charge or blocked sender",
                            false,
                        );
                    }
                    usleep($this->sleep * 1000 * 1000);
                } else {
                    $this->writeLine(' ' . $errorMessage, false);
                }
            }
        } while (count($memberIds) === $dbResultLimit);
    }
}
