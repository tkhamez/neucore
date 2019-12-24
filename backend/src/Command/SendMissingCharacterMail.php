<?php

declare(strict_types=1);

namespace Neucore\Command;

use Neucore\Entity\CorporationMember;
use Neucore\Entity\SystemVariable;
use Neucore\Repository\CorporationMemberRepository;
use Neucore\Repository\SystemVariableRepository;
use Neucore\Traits\EsiRateLimited;
use Neucore\Command\Traits\LogOutput;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EveMail;
use Neucore\Service\ObjectManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendMissingCharacterMail extends Command
{
    use LogOutput;
    use EsiRateLimited;

    /**
     * @var EveMail
     */
    private $eveMail;

    /**
     * @var CorporationMemberRepository
     */
    private $corporationMemberRepository;

    /**
     * @var SystemVariableRepository
     */
    private $sysVarRepository;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var int
     */
    private $sleep;

    public function __construct(
        EveMail $eveMail,
        RepositoryFactory $repositoryFactory,
        ObjectManager $objectManager,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($repositoryFactory->getSystemVariableRepository());

        $this->eveMail = $eveMail;
        $this->corporationMemberRepository = $repositoryFactory->getCorporationMemberRepository();
        $this->sysVarRepository = $repositoryFactory->getSystemVariableRepository();
        $this->objectManager = $objectManager;
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
                20
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
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
        if (! $daysVar || (int) $daysVar->getValue() <= 0) {
            $this->writeLine(' Invalid config.', false);
            return;
        }
        $days = (int) $daysVar->getValue();

        $dbResultLimit = 1000;
        $offset = $dbResultLimit * -1;
        do {
            $offset += $dbResultLimit;
            $memberIds = array_map(function (CorporationMember $player) {
                return $player->getId();
            }, $this->corporationMemberRepository->findByCorporationsWithoutAccountAndActive(
                $this->eveMail->missingCharacterGetCorporations(),
                $days,
                $dbResultLimit,
                $offset
            ));
            $this->objectManager->clear(); // detaches all objects from Doctrine

            foreach ($memberIds as $memberId) {
                if (! $this->objectManager->isOpen()) {
                    $this->logger->critical('SendInvalidTokenMail: cannot continue without an open entity manager.');
                    break;
                }
                $this->checkErrorLimit();

                $mayNotSendReason = $this->eveMail->missingCharacterMaySend($memberId);
                if ($mayNotSendReason !== '') {
                    continue;
                }

                $errMessage = $this->eveMail->missingCharacterSend($memberId);
                if (
                    $errMessage === '' || // success
                    strpos($errMessage, 'ContactCostNotApproved') !== false || // CSPA charge > 0
                    strpos($errMessage, 'ContactOwnerUnreachable') !== false // sender is blocked
                ) {
                    $this->eveMail->missingCharacterMailSent($memberId);
                    if ($errMessage === '') {
                        $this->writeLine('  Mail sent to ' . $memberId);
                    } else {
                        $this->writeLine(
                            "  Mail could not be sent to $memberId because of CSPA charge or blocked sender"
                        );
                    }
                    usleep($this->sleep * 1000 * 1000);
                } else {
                    $this->writeLine(' ' . $errMessage, false);
                }
            }
        } while (count($memberIds) === $dbResultLimit);
    }
}
