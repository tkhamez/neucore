<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EsiType;
use Neucore\Entity\EveLogin;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\Context;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdMembertracking200Ok;
use Swagger\Client\Eve\Model\PostUniverseNames200Ok;

class MemberTracking
{
    private LoggerInterface $log;

    private EsiApiFactory $esiApiFactory;

    private RepositoryFactory $repositoryFactory;

    private EntityManager $entityManager;

    private EsiData $esiData;

    private OAuthToken $oauthToken;

    private string $datasource;

    public function __construct(
        LoggerInterface $log,
        EsiApiFactory $esiApiFactory,
        RepositoryFactory $repositoryFactory,
        EntityManager $entityManager,
        EsiData $esiData,
        OAuthToken $oauthToken,
        Config $config
    ) {
        $this->log = $log;
        $this->esiApiFactory = $esiApiFactory;
        $this->repositoryFactory = $repositoryFactory;
        $this->entityManager = $entityManager;
        $this->esiData = $esiData;
        $this->oauthToken = $oauthToken;

        $this->datasource = (string)$config['eve']['datasource'];
    }

    /**
     * @return GetCorporationsCorporationIdMembertracking200Ok[]|null Null if ESI request failed
     */
    public function fetchData(string $accessToken, int $corporationId): ?array
    {
        $corpApi = $this->esiApiFactory->getCorporationApi($accessToken);
        try {
            $memberTracking = $corpApi->getCorporationsCorporationIdMembertracking($corporationId, $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return null;
        }

        return is_array($memberTracking) ? $memberTracking : null;
    }

    public function fetchCharacterNames(array $charIds): array
    {
        $charNames = [];
        foreach ($this->esiData->fetchUniverseNames($charIds) as $name) {
            /** @noinspection PhpCastIsUnnecessaryInspection */
            $charNames[(int)$name->getId()] = $name->getName();
        }
        return $charNames;
    }

    /**
     * Resolves ESI IDs to names and creates/updates database entries.
     *
     * This flushes and clears the ObjectManager every 100 IDs.
     */
    public function updateNames(array $typeIds, array $systemIds, array $stationIds, int $sleep = 0): void
    {
        // get ESI data
        $esiNames = []; /* @var PostUniverseNames200Ok[] $esiNames */

        // Do not request different types at once, that may lead to errors from ESI.
        $typeNames = $this->esiData->fetchUniverseNames($typeIds);
        $systemNames = $this->esiData->fetchUniverseNames($systemIds);
        $stationNames = $this->esiData->fetchUniverseNames($stationIds);

        foreach (array_merge($typeNames, $systemNames, $stationNames) as $name) {
            if (! in_array($name->getCategory(), [
                PostUniverseNames200Ok::CATEGORY_INVENTORY_TYPE,
                PostUniverseNames200Ok::CATEGORY_SOLAR_SYSTEM,
                PostUniverseNames200Ok::CATEGORY_STATION
            ])) {
                continue;
            }
            $esiNames[$name->getId()] = $name;
        }

        // create database entries
        $allIds = [
            PostUniverseNames200Ok::CATEGORY_INVENTORY_TYPE => $typeIds,
            EsiLocation::CATEGORY_SYSTEM => $systemIds,
            EsiLocation::CATEGORY_STATION => $stationIds
        ];
        $num = 0;
        foreach ($allIds as $category => $ids) {
            foreach ($ids as $id) {
                if ($category === PostUniverseNames200Ok::CATEGORY_INVENTORY_TYPE) {
                    $entity = $this->repositoryFactory->getEsiTypeRepository()->find($id);
                } else {
                    $entity = $this->repositoryFactory->getEsiLocationRepository()->find($id);
                }
                if ($entity === null) {
                    if ($category === PostUniverseNames200Ok::CATEGORY_INVENTORY_TYPE) {
                        $entity = new EsiType();
                    } else {
                        $entity = new EsiLocation();
                    }
                    $entity->setId($id);
                    $this->entityManager->persist($entity);
                }
                if ($entity instanceof EsiLocation) {
                    try {
                        $entity->setLastUpdate(new \DateTime());
                    } catch (\Exception) {
                        // ignore
                    }
                }
                if (isset($esiNames[$id])) {
                    $entity->setName($esiNames[$id]->getName());
                }
                if ($entity instanceof EsiLocation &&
                    in_array($category, [EsiLocation::CATEGORY_SYSTEM, EsiLocation::CATEGORY_STATION])
                ) {
                    $entity->setCategory($category);
                }

                $num++;
                if ($num % 100 === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }

                usleep($sleep * 1000);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Creat or update a structure from member tracking data.
     *
     *  This method does not flush the entity manager.
     *
     * @param GetCorporationsCorporationIdMembertracking200Ok $memberData
     * @param EsiToken|null $esiToken Director char access token as primary token to resolve structure IDs to names.
     */
    public function updateStructure(
        GetCorporationsCorporationIdMembertracking200Ok $memberData,
        ?EsiToken $esiToken
    ): void {
        $structureId = (int)$memberData->getLocationId();

        // fetch ESI data, try director token first, then character's token if available
        $location = null;
        if ($esiToken) {
            $directorAccessToken = $this->oauthToken->updateEsiToken($esiToken);
            if ($directorAccessToken !== null) {
                $location = $this->esiData->fetchStructure(
                    $structureId,
                    $directorAccessToken->getToken(),
                    false,
                    false
                );
            }
        }
        if (!$location || empty($location->getName())) {
            $character = $this->repositoryFactory->getCharacterRepository()->find($memberData->getCharacterId());
            if ($character !== null) {
                $characterAccessToken = $this->oauthToken->getToken($character, EveLogin::NAME_DEFAULT);
                $this->esiData->fetchStructure($structureId, $characterAccessToken, true, false);
            }
        }
    }

    /**
     * Stores the member data for one corporation.
     *
     * This flushes and clears the ObjectManager every 100 members.
     *
     * @param int $corporationId
     * @param GetCorporationsCorporationIdMembertracking200Ok[] $trackingData
     * @param array $charNames
     * @param int $sleep
     */
    public function storeMemberData(int $corporationId, array $trackingData, array $charNames, int $sleep = 0): void
    {
        $corporation = null;
        foreach ($trackingData as $num => $data) {
            if (!$this->entityManager->isOpen()) {
                $this->log->critical('MemberTracking::processData: cannot continue without an open entity manager.');
                break;
            }
            if ($corporation === null) {
                $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
            }
            if ($corporation === null) {
                $this->log->error("MemberTracking::storeMemberData: Corporation $corporationId not found");
                break;
            }

            /** @noinspection PhpCastIsUnnecessaryInspection */
            $id = (int)$data->getCharacterId();
            $corpMember = $this->repositoryFactory->getCorporationMemberRepository()->find($id);
            $character = $this->repositoryFactory->getCharacterRepository()->find($id);
            if ($corpMember === null) {
                $corpMember = new CorporationMember();
                $corpMember->setId($id);
            }
            if ($character !== null) {
                $corpMember->setMissingCharacterMailSentNumber(0); // reset count
            }
            if (isset($charNames[$id])) {
                $corpMember->setName($charNames[$id]);
            }
            if ($data->getLocationId() !== null) {
                $location = $this->repositoryFactory->getEsiLocationRepository()->find($data->getLocationId());
                $corpMember->setLocation($location);
            } else {
                $corpMember->setLocation(null);
            }
            if ($data->getLogoffDate() instanceof \DateTime) {
                $corpMember->setLogoffDate($data->getLogoffDate());
            }
            if ($data->getLogonDate() instanceof \DateTime) {
                $corpMember->setLogonDate($data->getLogonDate());
            }
            if ($data->getShipTypeId() !== null) {
                $type = $this->repositoryFactory->getEsiTypeRepository()->find($data->getShipTypeId());
                $corpMember->setShipType($type);
            } else {
                $corpMember->setShipType();
            }
            if ($data->getStartDate() instanceof \DateTime) {
                $corpMember->setStartDate($data->getStartDate());
            }
            $corpMember->setCorporation($corporation);

            $this->entityManager->persist($corpMember);
            if ($num > 0 && $num % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(); // clear to free memory
                $corporation = null; // not usable anymore after clear()
            }

            usleep($sleep * 1000);
        }

        $this->entityManager->flush();
    }
}
