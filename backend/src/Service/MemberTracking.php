<?php

declare(strict_types=1);

namespace Neucore\Service;

use Eve\Sso\AuthenticationProvider;
use Eve\Sso\EveAuthentication;
use Eve\Sso\InvalidGrantException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\ResourceOwnerAccessTokenInterface;
use Neucore\Data\DirectorToken;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiType;
use Neucore\Entity\EveLogin;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\Context;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdMembertracking200Ok;
use Swagger\Client\Eve\Model\PostUniverseNames200Ok;

class MemberTracking
{
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
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var EsiData
     */
    private $esiData;

    /**
     * @var OAuthToken
     */
    private $oauthToken;

    /**
     * @var AuthenticationProvider
     */
    private $authenticationProvider;

    /**
     * @var string
     */
    private $datasource;

    public function __construct(
        LoggerInterface $log,
        EsiApiFactory $esiApiFactory,
        RepositoryFactory $repositoryFactory,
        EntityManager $entityManager,
        EsiData $esiData,
        OAuthToken $oauthToken,
        AuthenticationProvider $authenticationProvider,
        Config $config
    ) {
        $this->log = $log;
        $this->esiApiFactory = $esiApiFactory;
        $this->repositoryFactory = $repositoryFactory;
        $this->entityManager = $entityManager;
        $this->esiData = $esiData;
        $this->oauthToken = $oauthToken;
        $this->authenticationProvider = $authenticationProvider;

        $this->datasource = $config['eve']['datasource'];
    }

    /**
     * @return bool True if character has director roles and could be stored
     */
    public function fetchCharacterAndStoreDirector(EveAuthentication $eveAuth): bool
    {
        // get corporation ID from character
        try {
            $char = $this->esiApiFactory->getCharacterApi()
                ->getCharactersCharacterId($eveAuth->getCharacterId(), $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return false;
        }
        if (!$char instanceof GetCharactersCharacterIdOk) {
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

        $this->entityManager->remove($character);
        if ($token) {
            $this->entityManager->remove($token);
        }

        return $this->entityManager->flush();
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
        if (! isset($data[SystemVariable::VALUE_CHARACTER_ID])) {
            return false;
        }

        try {
            $char = $this->esiApiFactory->getCharacterApi()
                ->getCharactersCharacterId($data[SystemVariable::VALUE_CHARACTER_ID], $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return false;
        }
        if (!$char instanceof GetCharactersCharacterIdOk) {
            return false;
        }

        $corporation = $this->esiData->fetchCorporation($char->getCorporationId());
        if ($corporation === null) {
            return false;
        }

        $data[SystemVariable::VALUE_CHARACTER_NAME] = $char->getName();
        $data[SystemVariable::VALUE_CORPORATION_ID] = $corporation->getId();
        $data[SystemVariable::VALUE_CORPORATION_NAME] = $corporation->getName();
        $data[SystemVariable::VALUE_CORPORATION_TICKER] = $corporation->getTicker();

        $variable->setValue((string) \json_encode($data));

        return $this->entityManager->flush();
    }

    /**
     * @param string $name
     * @return array|null The value from the system variable plus "character_id"
     */
    public function getDirectorTokenVariableData(string $name): ?DirectorToken
    {
        $number = (int) explode('_', $name)[2];

        $systemVariableRepository = $this->repositoryFactory->getSystemVariableRepository();
        $characterVar = $systemVariableRepository->find(SystemVariable::DIRECTOR_CHAR . $number);
        $tokenVar = $systemVariableRepository->find(SystemVariable::DIRECTOR_TOKEN . $number);

        $characterData = \json_decode($characterVar ? $characterVar->getValue() : '', true);
        $tokenData = \json_decode($tokenVar ? $tokenVar->getValue() : '', true);
        if (
            !isset($characterData[SystemVariable::VALUE_CHARACTER_ID]) ||
            !isset($tokenData[SystemVariable::TOKEN_ACCESS])
        ) {
            return null;
        }

        $directorToken = new DirectorToken();
        $directorToken->access = $tokenData[SystemVariable::TOKEN_ACCESS];
        $directorToken->refresh = $tokenData[SystemVariable::TOKEN_REFRESH];
        $directorToken->expires = $tokenData[SystemVariable::TOKEN_EXPIRES];
        $directorToken->systemVariableName = SystemVariable::DIRECTOR_TOKEN . $number;
        $directorToken->characterId = $characterData[SystemVariable::VALUE_CHARACTER_ID];

        return $directorToken;
    }

    /**
     * @return ResourceOwnerAccessTokenInterface|null Token including the resource_owner_id property.
     */
    public function refreshDirectorToken(DirectorToken $tokenData): ?ResourceOwnerAccessTokenInterface
    {
        $existingToken = new AccessToken([
            OAuthToken::OPTION_ACCESS_TOKEN => $tokenData->access,
            OAuthToken::OPTION_REFRESH_TOKEN => $tokenData->refresh,
            OAuthToken::OPTION_EXPIRES => (int) $tokenData->expires,
        ]);
        try {
            $token = $this->authenticationProvider->refreshAccessToken($existingToken);
        } catch (InvalidGrantException $e) {
            // Delete invalid refresh token so that it cannot be used again.
            $systemVar = $this->repositoryFactory->getSystemVariableRepository()->find($tokenData->systemVariableName);
            if ($systemVar) {
                $systemVar->setValue((string) \json_encode(new DirectorToken()));
                $this->entityManager->flush();
            }
            return null;
        } catch (\RuntimeException $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            $token = $existingToken;
        }

        if ($tokenData->expires !== $token->getExpires()) {
            // Store updated tokens
            $systemVar = $this->repositoryFactory->getSystemVariableRepository()->find($tokenData->systemVariableName);
            if ($systemVar) {
                $sysData = \json_decode($systemVar->getValue(), true);
                $updated = new DirectorToken();
                $updated->access = $token->getToken();
                $updated->refresh = $token->getRefreshToken();
                $updated->expires = $token->getExpires();
                $updated->scopes = $sysData[SystemVariable::TOKEN_SCOPES];
                $systemVar->setValue((string) \json_encode($updated));
                $this->entityManager->flush();
            }
        }

        return new AccessToken([
            OAuthToken::OPTION_ACCESS_TOKEN => $token->getToken(),
            OAuthToken::OPTION_REFRESH_TOKEN => $token->getRefreshToken(),
            OAuthToken::OPTION_EXPIRES => $token->getExpires(),
            OAuthToken::OPTION_RESOURCE_OWNER_ID => $tokenData->characterId,
        ]);
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
            $charNames[(int) $name->getId()] = $name->getName();
        }
        return $charNames;
    }

    /**
     * Resolves ESI IDs to names and creates/updates database entries.
     *
     * This flushes and clears the ObjectManager every 100 IDs.
     *
     * @param array $typeIds
     * @param array $systemIds
     * @param array $stationIds
     * @param int $sleep
     */
    public function updateNames(array $typeIds, array $systemIds, array $stationIds, $sleep = 0): void
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
                    } catch (\Exception $e) {
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

                $num ++;
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
     * @param DirectorToken|null $tokenData Director char access token as primary token to resolve structure
     *        IDs to names.
     */
    public function updateStructure(
        GetCorporationsCorporationIdMembertracking200Ok $memberData,
        ?DirectorToken $tokenData
    ): void {
        $structureId = (int) $memberData->getLocationId();

        // fetch ESI data, try director token first, then character's token if available
        $location = null;
        if ($tokenData) {
            $directorAccessToken = $this->refreshDirectorToken($tokenData);
            if ($directorAccessToken !== null) {
                $location = $this->esiData->fetchStructure($structureId, $directorAccessToken->getToken(), false);
            }
        }
        if ($location === null) {
            $character = $this->repositoryFactory->getCharacterRepository()->find($memberData->getCharacterId());
            if ($character !== null) {
                $characterAccessToken = $this->oauthToken->getToken($character, EveLogin::NAME_DEFAULT);
                $location = $this->esiData->fetchStructure($structureId, $characterAccessToken, false);
            }
        }

        // if ESI failed, create location db entry with ID only
        if ($location === null && $this->repositoryFactory->getEsiLocationRepository()->find($structureId) === null) {
            $location = new EsiLocation();
            $location->setId($structureId);
            $location->setCategory(EsiLocation::CATEGORY_STRUCTURE);
            $this->entityManager->persist($location);
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
            if (! $this->entityManager->isOpen()) {
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
            $id = (int) $data->getCharacterId();
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

    private function storeDirector(EveAuthentication $eveAuth, Corporation $corporation): bool
    {
        $systemVariableRepository = $this->repositoryFactory->getSystemVariableRepository();

        // check if character already exists and determine next available number suffix
        $maxNumber = 0;
        $existingDirectors = [];
        foreach ($systemVariableRepository->getDirectors() as $existingDirector) {
            $number = (int) explode('_', $existingDirector->getName())[2];
            $maxNumber = max($maxNumber, $number);
            $value = \json_decode($existingDirector->getValue(), true);
            if ($value && isset($value[SystemVariable::VALUE_CHARACTER_ID])) {
                $existingDirectors[$value[SystemVariable::VALUE_CHARACTER_ID]] = [
                    'system_variable' => $existingDirector,
                    'number' => $number
                ];
            }
        }
        $directorNumber = $maxNumber + 1;

        // store new director
        $authCharacterId = $eveAuth->getCharacterId();
        $directorToken = null;
        if (isset($existingDirectors[$authCharacterId])) {
            $directorNumber = $existingDirectors[$authCharacterId]['number'];
            $directorChar = $existingDirectors[$authCharacterId]['system_variable'];
            $directorToken = $systemVariableRepository->find(SystemVariable::DIRECTOR_TOKEN . $directorNumber);
        } else {
            $directorChar = new SystemVariable(SystemVariable::DIRECTOR_CHAR . $directorNumber);
            $directorChar->setScope(SystemVariable::SCOPE_SETTINGS);
            $this->entityManager->persist($directorChar);
        }
        if ($directorToken === null) {
            $directorToken = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . $directorNumber);
            $this->entityManager->persist($directorToken);
        }
        $directorChar->setValue((string) json_encode([
            SystemVariable::VALUE_CHARACTER_ID => $authCharacterId,
            SystemVariable::VALUE_CHARACTER_NAME => $eveAuth->getCharacterName(),
            SystemVariable::VALUE_CORPORATION_ID => $corporation->getId(),
            SystemVariable::VALUE_CORPORATION_NAME => $corporation->getName(),
            SystemVariable::VALUE_CORPORATION_TICKER => $corporation->getTicker()
        ]));
        $directorToken->setScope(SystemVariable::SCOPE_BACKEND);
        $token = new DirectorToken();
        $token->access = $eveAuth->getToken()->getToken();
        $token->refresh = $eveAuth->getToken()->getRefreshToken();
        $token->expires = $eveAuth->getToken()->getExpires();
        $token->scopes = $eveAuth->getScopes();
        $directorToken->setValue((string) json_encode($token));

        return $this->entityManager->flush();
    }
}
