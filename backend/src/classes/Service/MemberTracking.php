<?php declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiType;
use Neucore\Entity\SystemVariable;
use Neucore\EsiRateLimitedTrait;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Brave\Sso\Basics\EveAuthentication;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\ResourceOwnerAccessTokenInterface;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdRolesOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdMembertracking200Ok;
use Swagger\Client\Eve\Model\PostUniverseNames200Ok;

class MemberTracking
{
    use EsiRateLimitedTrait;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var EsiApiFactory
     */
    private $esiApiFactory;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var EsiData
     */
    private $esiData;

    /**
     * @var OAuthToken
     */
    private $oauthToken;

    /**
     * @var string
     */
    private $datasource;

    public function __construct(
        LoggerInterface $log,
        EsiApiFactory $esiApiFactory,
        RepositoryFactory $repositoryFactory,
        ObjectManager $objectManager,
        EsiData $esiData,
        OAuthToken $oauthToken,
        Config $config
    ) {
        $this->esiRateLimited($repositoryFactory->getSystemVariableRepository());

        $this->log = $log;
        $this->esiApiFactory = $esiApiFactory;
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManager = $objectManager;
        $this->esiData = $esiData;
        $this->oauthToken = $oauthToken;

        $this->datasource = $config['eve']['datasource'];
    }

    /**
     * @return bool True if character has director roles and could be stored
     */
    public function verifyAndStoreDirector(EveAuthentication $eveAuth): bool
    {
        // get corporation ID from character
        try {
            $char = $this->esiApiFactory->getCharacterApi()
                ->getCharactersCharacterId((int) $eveAuth->getCharacterId(), $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return false;
        }

        // check if character has required roles
        if (! $this->verifyDirectorRole((int) $eveAuth->getCharacterId(), $eveAuth->getToken()->getToken())) {
            return false;
        }

        // get corporation - adds it to DB if missing
        $corporation = $this->esiData->fetchCorporation($char->getCorporationId());
        if ($corporation === null) {
            return false;
        }

        // store director
        return $this->storeDirector($eveAuth, $corporation);
    }

    public function removeDirector(SystemVariable $character): bool
    {
        $number = (int) explode('_', $character->getName())[2];
        $token = $this->repositoryFactory->getSystemVariableRepository()
            ->find(SystemVariable::DIRECTOR_TOKEN . $number);

        $this->objectManager->remove($character);
        if ($token) {
            $this->objectManager->remove($token);
        }

        return $this->objectManager->flush();
    }

    /**
     * Updates name and corporation of director char.
     */
    public function updateDirector(string $variableName): bool
    {
        $variable = $this->repositoryFactory->getSystemVariableRepository()->find($variableName);
        if (! $variable) {
            return false;
        }
        $data = \json_decode($variable->getValue(), true);
        if (! isset($data['character_id'])) {
            return false;
        }

        try {
            $char = $this->esiApiFactory->getCharacterApi()
                ->getCharactersCharacterId($data['character_id'], $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return false;
        }

        $corporation = $this->esiData->fetchCorporation($char->getCorporationId());
        if ($corporation === null) {
            return false;
        }

        $data['character_name'] = $char->getName();
        $data['corporation_id'] = $corporation->getId();
        $data['corporation_name'] = $corporation->getName();
        $data['corporation_ticker'] = $corporation->getTicker();

        $variable->setValue((string) \json_encode($data));

        return $this->objectManager->flush();
    }

    /**
     * @param string $name
     * @return ResourceOwnerAccessTokenInterface|null Token including the resource_owner_id property.
     */
    public function refreshDirectorToken(string $name): ?ResourceOwnerAccessTokenInterface
    {
        $number = (int) explode('_', $name)[2];

        $systemVariableRepository = $this->repositoryFactory->getSystemVariableRepository();
        $characterVar = $systemVariableRepository->find(SystemVariable::DIRECTOR_CHAR . $number);
        $tokenVar = $systemVariableRepository->find(SystemVariable::DIRECTOR_TOKEN . $number);

        $characterData = \json_decode($characterVar ? $characterVar->getValue() : '', true);
        $tokenData = \json_decode($tokenVar ? $tokenVar->getValue() : '', true);
        if (! isset($characterData['character_id']) || ! isset($tokenData['access'])) {
            return null;
        }

        try {
            $token = $this->oauthToken->refreshAccessToken(new AccessToken([
                'access_token' => $tokenData['access'],
                'refresh_token' => $tokenData['refresh'],
                'expires' => (int) $tokenData['expires'],
            ]));
        } catch (IdentityProviderException $e) {
            return null;
        }

        return new AccessToken([
            'access_token' => $token->getToken(),
            'refresh_token' => $token->getRefreshToken(),
            'expires' => $token->getExpires(),
            'resource_owner_id' => $characterData['character_id'],
        ]);
    }

    public function verifyDirectorRole(int $characterId, string $accessToken): bool
    {
        $characterApi = $this->esiApiFactory->getCharacterApi($accessToken);
        try {
            $roles = $characterApi->getCharactersCharacterIdRoles($characterId, $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return false;
        }

        if (! $roles instanceof GetCharactersCharacterIdRolesOk ||
            ! is_array($roles->getRoles()) ||
            ! in_array('Director', $roles->getRoles())
        ) {
            return false;
        }

        return true;
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
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return null;
        }

        return $memberTracking;
    }

    /**
     * @param int $corporationId
     * @param GetCorporationsCorporationIdMembertracking200Ok[] $trackingData
     * @param int $sleep milliseconds
     */
    public function processData(int $corporationId, array $trackingData, $sleep = 0): void
    {
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
            } else { // structures - there should be nothing else (I think)
                $structures[] = $item;
            }
        }
        $typeIds = array_unique($typeIds);
        $systemIds = array_unique($systemIds);
        $stationIds = array_unique($stationIds);

        // delete members that left
        $this->repositoryFactory->getCorporationMemberRepository()->removeFormerMembers($corporationId, $charIds);

        $this->updateNames($typeIds, $systemIds, $stationIds, $sleep);
        $this->updateStructures($structures, $sleep);
        $charNames = $this->fetchCharacterNames($charIds);

        $this->storeMemberData($corporationId, $trackingData, $charNames, $sleep);
    }

    private function fetchCharacterNames(array $charIds): array
    {
        $charNames = [];
        foreach ($this->esiData->fetchUniverseNames($charIds) as $name) {
            $charNames[(int) $name->getId()] = $name->getName();
        }
        return $charNames;
    }

    private function updateNames(array $typeIds, array $systemIds, array $stationIds, $sleep): void
    {
        // get ESI data
        $esiNames = []; /* @var PostUniverseNames200Ok[] $esiNames */
        foreach ($this->esiData->fetchUniverseNames(array_merge($typeIds, $systemIds, $stationIds)) as $name) {
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
                    $this->objectManager->persist($entity);
                }
                if (isset($esiNames[$id])) {
                    $entity->setName($esiNames[$id]->getName());
                }
                if (in_array($category, [EsiLocation::CATEGORY_SYSTEM, EsiLocation::CATEGORY_STATION])) {
                    $entity->setCategory($category);
                }

                $num ++;
                if ($num % 100 === 0) {
                    $this->objectManager->flush();
                    $this->objectManager->clear();
                }

                usleep($sleep * 1000);
            }
        }
    }

    /**
     * @param GetCorporationsCorporationIdMembertracking200Ok[] $memberData
     * @param int $sleep
     */
    private function updateStructures(array $memberData, int $sleep): void
    {
        // create/update db entries
        foreach ($memberData as $num => $member) {
            if (! $this->objectManager->isOpen()) {
                $this->log->critical('UpdateCharacters: cannot continue without an open entity manager.');
                break;
            }
            $this->checkErrorLimit();

            $structureId = (int) $member->getLocationId();

            // fetch ESI data
            $location = null;
            $character = $this->repositoryFactory->getCharacterRepository()->find($member->getCharacterId());
            if ($character !== null) {
                $token = $this->oauthToken->getToken($character);
                $location = $this->esiData->fetchStructure($structureId, $token, false);
            }

            // if ESI failed, create location db entry with ID only
            if ($location === null) {
                if ($this->repositoryFactory->getEsiLocationRepository()->find($structureId) === null) {
                    $location = new EsiLocation();
                    $location->setId($structureId);
                    $location->setCategory(EsiLocation::CATEGORY_STRUCTURE);
                    $this->objectManager->persist($location);
                }
            }

            if ($num > 0 && $num % 20 === 0) {
                $this->objectManager->flush();
                $this->objectManager->clear();
            }

            usleep($sleep * 1000);
        }
    }

    /**
     * @param int $corporationId
     * @param GetCorporationsCorporationIdMembertracking200Ok[] $trackingData
     * @param array $charNames
     * @param int $sleep
     */
    private function storeMemberData(int $corporationId, array $trackingData, array $charNames, int $sleep): void
    {
        $corporation = null;
        foreach ($trackingData as $num => $data) {
            if (! $this->objectManager->isOpen()) {
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

            $id = (int) $data->getCharacterId();
            $corpMember = $this->repositoryFactory->getCorporationMemberRepository()->find($id);
            $character = $this->repositoryFactory->getCharacterRepository()->find($id);
            if ($corpMember === null) {
                $corpMember = new CorporationMember();
                $corpMember->setId($id);
            }
            $corpMember->setCharacter($character);
            $corpMember->setName($charNames[$id] ?? null);
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
                $corpMember->setShipType(null);
            }
            if ($data->getStartDate() instanceof \DateTime) {
                $corpMember->setStartDate($data->getStartDate());
            }
            $corpMember->setCorporation($corporation);

            $this->objectManager->persist($corpMember);
            if ($num > 0 && $num % 100 === 0) {
                $this->objectManager->flush();
                $this->objectManager->clear(); // clear to free memory
                $corporation = null; // not usable anymore after clear()
            }

            usleep($sleep * 1000);
        }

        $this->objectManager->flush();
    }

    private function storeDirector(EveAuthentication $eveAuth, Corporation $corporation): bool
    {
        // determine next available number suffix
        $existingDirectors = $this->repositoryFactory->getSystemVariableRepository()->getDirectors();
        $maxNumber = 0;
        foreach ($existingDirectors as $existingDirector) {
            $number = (int) explode('_', $existingDirector->getName())[2];
            $maxNumber = max($maxNumber, $number);
        }
        $nextNumber = $maxNumber + 1;

        // store new director
        $newDirectorChar = new SystemVariable(SystemVariable::DIRECTOR_CHAR . $nextNumber);
        $newDirectorChar->setScope(SystemVariable::SCOPE_SETTINGS);
        $newDirectorChar->setValue((string) json_encode([
            'character_id' => (int) $eveAuth->getCharacterId(),
            'character_name' => $eveAuth->getCharacterName(),
            'corporation_id' => $corporation->getId(),
            'corporation_name' => $corporation->getName(),
            'corporation_ticker' => $corporation->getTicker(),
        ]));
        $newDirectorToken = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . $nextNumber);
        $newDirectorToken->setScope(SystemVariable::SCOPE_BACKEND);
        $newDirectorToken->setValue((string) json_encode([
            'access' => $eveAuth->getToken()->getToken(),
            'refresh' => $eveAuth->getToken()->getRefreshToken(),
            'expires' => $eveAuth->getToken()->getExpires(),
        ]));

        $this->objectManager->persist($newDirectorChar);
        $this->objectManager->persist($newDirectorToken);
        return $this->objectManager->flush();
    }
}
