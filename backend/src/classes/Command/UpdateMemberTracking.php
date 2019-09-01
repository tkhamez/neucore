<?php declare(strict_types=1);

namespace Neucore\Command;

use Neucore\EsiRateLimitedTrait;
use Neucore\Command\Traits\LogOutput;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EsiData;
use Neucore\Service\MemberTracking;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateMemberTracking extends Command
{
    use LogOutput;
    use EsiRateLimitedTrait;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var MemberTracking
     */
    private $memberTracking;

    /**
     * @var EsiData
     */
    private $esiData;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        MemberTracking $memberTracking,
        EsiData $esiData,
        LoggerInterface $logger
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($repositoryFactory->getSystemVariableRepository());

        $this->repositoryFactory = $repositoryFactory;
        $this->memberTracking = $memberTracking;
        $this->esiData = $esiData;
    }

    protected function configure()
    {
        $this->setName('update-member-tracking')
            ->setDescription(
                'Updates member tracking data from all available characters with director role from settings.'
            )
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
        $sleep = intval($input->getOption('sleep'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('Started "update-member-tracking"', false);

        $systemVariableRepository = $this->repositoryFactory->getSystemVariableRepository();
        foreach ($systemVariableRepository->getDirectors() as $characterVariable) {
            $this->checkErrorLimit();

            $character = \json_decode($characterVariable->getValue());
            if ($character === null) {
                $this->writeLine('  Error obtaining character data from ' . $characterVariable->getName(), false);
                continue;
            }

            $corporation = $this->repositoryFactory->getCorporationRepository()->find($character->corporation_id);
            if ($corporation === null) {
                $this->writeLine('  Corporation not found for ' . $characterVariable->getName(), false);
                continue;
            }

            $token = $this->memberTracking->refreshDirectorToken($characterVariable->getName());
            if ($token === null) {
                $this->writeLine('  Error refreshing token for ' . $characterVariable->getName(), false);
                continue;
            }

            $trackingData = $this->memberTracking->fetchData($token->getToken(), (int) $corporation->getId());
            if (! is_array($trackingData)) {
                $this->writeLine(
                    '  Error getting member tracking data from ESI for ' . $characterVariable->getName(),
                    false
                );
                continue;
            }
            $this->memberTracking->processData((int) $corporation->getId(), $trackingData, $sleep);

            $this->writeLine(
                '  Updated tracking data for ' . count($trackingData) .
                ' members of corporation ' . $corporation->getId()
            );

            usleep($sleep * 1000);
        }

        $this->writeLine('Finished "update-member-tracking"', false);
    }
}
