<?php declare(strict_types=1);

namespace Brave\Core\Command;

use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\EsiData;
use Brave\Core\Service\MemberTracking;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateMemberTracking extends Command
{
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
        MemberTracking $memberTracking,
        EsiData $esiData,
        LoggerInterface $logger
    ) {
        parent::__construct();

        $this->repositoryFactory = $repositoryFactory;
        $this->memberTracking = $memberTracking;
        $this->esiData = $esiData;
        $this->logger = $logger;
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
                200
            )
            ->addOption('log', 'l', InputOption::VALUE_NONE, 'Redirect output to log.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sleep = intval($input->getOption('sleep'));
        $this->log = (bool) $input->getOption('log');
        $this->output = $output;

        $this->writeln('* Started "update-member-tracking"');

        $systemVariableRepository = $this->repositoryFactory->getSystemVariableRepository();
        foreach ($systemVariableRepository->getDirectors() as $characterVariable) {
            $character = \json_decode($characterVariable->getValue());
            if ($character === null) {
                $this->writeln('Error obtaining character data from ' . $characterVariable->getName());
                continue;
            }

            $corporation = $this->repositoryFactory->getCorporationRepository()->find($character->corporation_id);
            if ($corporation === null) {
                $this->writeln('Corporation not found for ' . $characterVariable->getName());
                continue;
            }

            $token = $this->memberTracking->refreshDirectorToken($characterVariable->getName());
            if ($token === null) {
                $this->writeln('Error refreshing token for ' . $characterVariable->getName());
                continue;
            }

            $trackingData = $this->memberTracking->fetchData($token->getToken(), (int) $corporation->getId());
            if (! is_array($trackingData)) {
                $this->writeln('Error getting member tracking data from ESI for ' . $characterVariable->getName());
                continue;
            }
            $this->memberTracking->processData($corporation, $trackingData);

            $this->writeln(
                'Updated tracking data for ' . count($trackingData) .
                ' members of corporation ' . $corporation->getId()
            );

            usleep($sleep * 1000);
        }

        $this->writeln('* Finished "update-member-tracking"');
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
