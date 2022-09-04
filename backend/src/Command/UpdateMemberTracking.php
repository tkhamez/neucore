<?php

declare(strict_types=1);

namespace Neucore\Command;

use Doctrine\ORM\EntityManagerInterface;
use Neucore\Command\Traits\EsiRateLimited;
use Neucore\Command\Traits\LogOutput;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\MemberTracking;
use Neucore\Service\OAuthToken;
use Neucore\Storage\StorageInterface;
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

    private RepositoryFactory $repositoryFactory;

    private MemberTracking $memberTracking;

    private OAuthToken $oauthToken;

    private EntityManagerInterface $entityManager;

    /**
     * Time in milliseconds
     */
    private int $sleep;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        MemberTracking $memberTracking,
        OAuthToken $oauthToken,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        StorageInterface $storage
    ) {
        parent::__construct();
        $this->logOutput($logger);
        $this->esiRateLimited($storage, $logger);

        $this->repositoryFactory = $repositoryFactory;
        $this->memberTracking = $memberTracking;
        $this->oauthToken = $oauthToken;
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
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
                '50'
            );
        $this->configureLogOutput($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $corpId = intval($input->getArgument('corporation'));
        $this->sleep = intval($input->getOption('sleep'));
        $this->executeLogOutput($input, $output);

        $this->writeLine('Started "update-member-tracking"', false);

        $corporationRepository = $this->repositoryFactory->getCorporationRepository();
        $processedCorporations = [];
        foreach ($this->getValidDirectorTokens() as $esiToken) {
            $this->checkForErrors();

            $character = $esiToken->getCharacter();
            if ($character === null) { // Should not be possible
                continue;
            }

            $corporation = $character->getCorporation();
            if ($corporation === null) {
                $this->writeLine('  Corporation not found for ' . $character->getName(), false);
                continue;
            }
            $corporationId = $character->getCorporation()->getId();

            if (
                in_array($corporation->getId(), $processedCorporations) || // don't process the same corp twice
                ($corpId > 0 && $corpId !== $corporation->getId())
            ) {
                continue;
            }

            $token = $this->oauthToken->updateEsiToken($esiToken);
            if ($token === null) {
                $this->writeLine('  Error refreshing token for ' . $character->getName(), false);
                continue;
            }

            $this->writeLine('  Start updating ' . $corporation->getName(), false);

            $trackingData = $this->memberTracking->fetchData($token->getToken(), $corporation->getId());
            if (!is_array($trackingData)) {
                $this->writeLine(
                    '  Error getting member tracking data from ESI for ' . $character->getName(),
                    false
                );
                continue;
            }

            $directorTokenWithStructureScope = null;
            if (in_array(EveLogin::SCOPE_STRUCTURES, $this->oauthToken->getScopesFromToken($esiToken))) {
                $directorTokenWithStructureScope = $esiToken;
            }
            $this->processData($corporation->getId(), $trackingData, $directorTokenWithStructureScope);

            // set last update date - get corp again because "processData" may clear the ObjectManager
            $corporation = $corporationRepository->find($corporationId);
            if ($corporation === null) {
                $this->writeLine('  Corporation not found for ' . $character->getName(), false);
                continue;
            }

            $corporation->setTrackingLastUpdate(new \DateTime());
            $this->entityManager->flush();

            $this->writeLine(
                '  Updated tracking data for ' . count($trackingData) .
                ' members of corporation ' . $corporation->getId()
            );

            $processedCorporations[] = $corporation->getId();

            usleep($this->sleep * 1000);
        }

        $this->writeLine('Finished "update-member-tracking"', false);

        return 0;
    }

    /**
     * @return EsiToken[]
     */
    private function getValidDirectorTokens(): array
    {
        $eveLogin = $this->repositoryFactory->getEveLoginRepository()->findOneBy(['name' => EveLogin::NAME_TRACKING]);
        if (!$eveLogin) {
            return [];
        }

        return $this->repositoryFactory->getEsiTokenRepository()->findBy([
            'eveLogin' => $eveLogin,
            'validToken' => true,
            'hasRoles' => true,
        ]);
    }

    /**
     * @param int $corporationId
     * @param GetCorporationsCorporationIdMembertracking200Ok[] $trackingData
     * @param EsiToken|null $esiToken Used to resolve structure IDs to names if available
     */
    private function processData(int $corporationId, array $trackingData, ?EsiToken $esiToken): void
    {
        if (empty($trackingData)) {
            return;
        }

        // collect IDs
        $charIds = [];
        $typeIds = [];
        $systemIds = [];
        $stationIds = [];
        $structures = [];
        foreach ($trackingData as $item) {
            /** @noinspection PhpCastIsUnnecessaryInspection */
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

        $this->memberTracking->updateNames($typeIds, $systemIds, $stationIds, $this->sleep);
        $this->writeLine('  Updated ship/system/station names');

        $this->updateStructures($structures, $esiToken);
        $this->writeLine('  Updated structure names');

        $charNames = $this->memberTracking->fetchCharacterNames($charIds);

        $this->memberTracking->storeMemberData($corporationId, $trackingData, $charNames, $this->sleep);
    }

    /**
     * @param GetCorporationsCorporationIdMembertracking200Ok[] $structures
     */
    private function updateStructures(array $structures, ?EsiToken $esiToken): void
    {
        foreach ($structures as $num => $memberData) {
            if (!$this->entityManager->isOpen()) {
                $this->logger->critical('UpdateCharacters: cannot continue without an open entity manager.');
                break;
            }
            $this->checkForErrors();

            $this->memberTracking->updateStructure($memberData, $esiToken);

            if ($num > 0 && $num % 20 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }

            usleep($this->sleep * 1000);
        }

        $this->entityManager->flush();
    }
}
