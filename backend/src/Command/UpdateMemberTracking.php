<?php declare(strict_types=1);

namespace Neucore\Command;

use League\OAuth2\Client\Token\ResourceOwnerAccessTokenInterface;
use Neucore\Api;
use Neucore\Traits\EsiRateLimited;
use Neucore\Command\Traits\LogOutput;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EsiData;
use Neucore\Service\MemberTracking;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdMembertracking200Ok;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateMemberTracking extends Command
{
    use LogOutput;
    use EsiRateLimited;

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
            ->addArgument('corporation', InputArgument::OPTIONAL, 'Update only one corporation.')
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
        $corpId = intval($input->getArgument('corporation'));
        $sleep = intval($input->getOption('sleep'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('Started "update-member-tracking"', false);

        $systemVariableRepository = $this->repositoryFactory->getSystemVariableRepository();
        $processedCorporations = [];
        foreach ($systemVariableRepository->getDirectors() as $characterVariable) {
            $this->checkErrorLimit();

            $character = \json_decode($characterVariable->getValue());
            if ($character === null) {
                $this->writeLine('  Error obtaining character data from ' . $characterVariable->getName(), false);
                continue;
            }

            if (in_array($character->corporation_id, $processedCorporations) // don't process the same corp twice
                || $corpId > 0 && $corpId !== $character->corporation_id
            ) {
                continue;
            }

            $corporation = $this->repositoryFactory->getCorporationRepository()->find($character->corporation_id);
            if ($corporation === null) {
                $this->writeLine('  Corporation not found for ' . $characterVariable->getName(), false);
                continue;
            }

            $token = null;
            $tokenData = $this->memberTracking->getDirectorTokenVariableData($characterVariable->getName());
            if ($tokenData) {
                $token = $this->memberTracking->refreshDirectorToken($tokenData);
            }
            if ($token === null) {
                $this->writeLine('  Error refreshing token for ' . $characterVariable->getName(), false);
                continue;
            }

            $this->writeLine('  Start updating ' . $corporation->getId(), false);
            
            $trackingData = $this->memberTracking->fetchData($token->getToken(), (int) $corporation->getId());
            if (! is_array($trackingData)) {
                $this->writeLine(
                    '  Error getting member tracking data from ESI for ' . $characterVariable->getName(),
                    false
                );
                continue;
            }

            if (! isset($tokenData['scopes']) || ! in_array(Api::SCOPE_STRUCTURES, $tokenData['scopes'])) {
                $token = null;
            }
            $this->processData((int) $corporation->getId(), $trackingData, $sleep, $token);

            $this->writeLine(
                '  Updated tracking data for ' . count($trackingData) .
                ' members of corporation ' . $corporation->getId()
            );

            $processedCorporations[] = $corporation->getId();

            usleep($sleep * 1000);
        }

        $this->writeLine('Finished "update-member-tracking"', false);
    }

    /**
     * @param int $corporationId
     * @param GetCorporationsCorporationIdMembertracking200Ok[] $trackingData
     * @param int $sleep milliseconds
     * @param ResourceOwnerAccessTokenInterface|null $token Used to resolve structure IDs to names if available
     */
    private function processData(
        int $corporationId,
        array $trackingData,
        $sleep,
        ResourceOwnerAccessTokenInterface $token = null
    ): void {
        if (count($trackingData) === 0) {
            return;
        }

        // collect IDs
        $charIds = [];
        $typeIds = [];
        $systemIds = [];
        $stationIds = [];
        $structures = [];
        foreach ($trackingData as $item) {
            $charIds[] = (int) $item->getCharacterId();
            $typeIds[] = (int) $item->getShipTypeId();

            // see also https://github.com/esi/esi-docs/blob/master/docs/asset_location_id.md
            $locationId = (int) $item->getLocationId();
            if ($locationId >= 30000000 && $locationId <= 33000000) {
                $systemIds[] = $locationId;
            } elseif ($locationId >= 60000000 && $locationId <= 64000000) {
                $stationIds[] = $locationId;
            } else { // structures - there should be nothing else
                $structures[] = $item;
            }
        }
        $typeIds = array_unique($typeIds);
        $systemIds = array_unique($systemIds);
        $stationIds = array_unique($stationIds);

        // delete members that left
        $this->repositoryFactory->getCorporationMemberRepository()->removeFormerMembers($corporationId, $charIds);

        $this->memberTracking->updateNames($typeIds, $systemIds, $stationIds, $sleep);
        $this->writeLine('  Updated ship/system/station names');

        $this->memberTracking->updateStructures($structures, $token, $sleep);
        $this->writeLine('  Updated structure names');

        $charNames = $this->memberTracking->fetchCharacterNames($charIds);

        $this->memberTracking->storeMemberData($corporationId, $trackingData, $charNames, $sleep);
    }
}
