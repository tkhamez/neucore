<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\Context;
use Neucore\Service\Character as CharacterService;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\ApiException;
use Swagger\Client\Eve\Model\GetAlliancesAllianceIdOk;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdOk;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdRolesOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk;
use Swagger\Client\Eve\Model\GetUniverseStructuresStructureIdOk;
use Swagger\Client\Eve\Model\PostCharactersAffiliation200Ok;
use Swagger\Client\Eve\Model\PostUniverseNames200Ok;

/**
 * Fetch and process data from ESI.
 */
class EsiData
{
    public const CORPORATION_DOOMHEIM_ID = 1000001;

    private LoggerInterface $log;

    private EsiApiFactory $esiApiFactory;

    private ObjectManager $objectManager;

    private RepositoryFactory $repositoryFactory;

    private \Neucore\Service\Character $characterService;

    private ?int $lastErrorCode = null;

    private string $datasource;

    /**
     * @var int[]
     */
    private array $structuresUpdated = [];

    /**
     * Cache of SystemVariable::FETCH_STRUCTURE_ERROR_DAYS
     */
    private ?string $errorConfiguration = null;

    public function __construct(
        LoggerInterface $log,
        EsiApiFactory $esiApiFactory,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        CharacterService $characterService,
        Config $config
    ) {
        $this->log = $log;
        $this->esiApiFactory = $esiApiFactory;
        $this->objectManager = $objectManager;
        $this->repositoryFactory = $repositoryFactory;
        $this->characterService = $characterService;

        $this->datasource = $config['eve']['datasource'];
    }

    public function getLastErrorCode(): ?int
    {
        return $this->lastErrorCode;
    }

    /**
     * Updates character, corporation and alliance from ESI.
     *
     * Character must already exist in the local database.
     * Returns null if any of the ESI requests fails.
     *
     * @param int|null $id EVE character ID
     */
    public function fetchCharacterWithCorporationAndAlliance(?int $id): ?Character
    {
        $char = $this->fetchCharacter($id, false);
        if ($char === null || $char->getCorporation() === null) { // corp is never null here, but that's not obvious
            return null;
        }

        $corp = $this->fetchCorporation($char->getCorporation()->getId(), false);
        if ($corp === null) {
            return null;
        }

        if ($corp->getAlliance() !== null) {
            $alli = $this->fetchAlliance($corp->getAlliance()->getId(), false);
            if ($alli === null) {
                return null;
            }
        }

        if (! $this->objectManager->flush()) {
            return null;
        }

        return $char;
    }

    /**
     * Updates character from ESI.
     *
     * The character must already exist.
     *
     * If the character's corporation is not yet in the database it will
     * be created, but not updated with data from ESI.
     *
     * Returns null if the ESI requests fails or if the character
     * does not exist in the local database.
     *
     * @param int|null $id
     * @param bool $flush Optional write data to database, defaults to true
     * @return null|Character An instance that is attached to the Doctrine entity manager.
     */
    public function fetchCharacter(?int $id, bool $flush = true): ?Character
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        // get char from local database
        $char = $this->repositoryFactory->getCharacterRepository()->find($id);
        if ($char === null) {
            return null;
        }

        // Get data from character
        $this->lastErrorCode = null;
        $eveChar = null;
        $corpId = null;
        try {
            // ESI cache = 24 hours.
            // But maybe faster than /characters/affiliation/ if character was deleted.
            $eveChar = $this->esiApiFactory->getCharacterApi()->getCharactersCharacterId($id, $this->datasource);
        } catch (ApiException $e) {
            // Do not log and continue if character was deleted/biomassed
            $body = $e->getResponseBody();
            if ($e->getCode() === 404 && is_string($body) && strpos($body, 'Character has been deleted') !== false) {
                $corpId = self::CORPORATION_DOOMHEIM_ID;
            } else {
                $this->lastErrorCode = $e->getCode();
                $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
                return null;
            }
        } catch (\Exception $e) { // e.g. InvalidArgumentException
            $this->lastErrorCode = $e->getCode();
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return null;
        }

        $updated = false;

        // update char (and player) name
        if ($eveChar instanceof GetCharactersCharacterIdOk) {
            /** @noinspection PhpCastIsUnnecessaryInspection */
            $this->characterService->setCharacterName($char, (string)$eveChar->getName());
            if ($char->getMain()) {
                $char->getPlayer()->setName($char->getName());
            }
            $updated = true;
        }

        // Update char with corp entity - ESI cache = 1 hour.
        // But maybe slower than /characters/{character_id}/ if character was deleted.
        if (!$corpId) {
            $affiliation = $this->fetchCharactersAffiliation([$id]);
            if (isset($affiliation[0])) {
                /** @noinspection PhpCastIsUnnecessaryInspection */
                $corpId = (int) $affiliation[0]->getCorporationId();
            } elseif ($eveChar instanceof GetCharactersCharacterIdOk) {
                /** @noinspection PhpCastIsUnnecessaryInspection */
                $corpId = (int) $eveChar->getCorporationId();
            }
        }
        if ($corpId) {
            $corp = $this->getCorporationEntity($corpId);
            $char->setCorporation($corp);
            $corp->addCharacter($char);
            $updated = true;
        }

        if ($updated) {
            try {
                $char->setLastUpdate(new \DateTime());
            } catch (\Exception $e) {
                // ignore
            }
        }

        // flush
        if ($flush && !$this->objectManager->flush()) {
            return null;
        }

        return $char;
    }

    /**
     * @param array $ids Valid IDs
     * @return PostCharactersAffiliation200Ok[]
     * @see https://esi.evetech.net/ui/#/Character/post_characters_affiliation
     */
    public function fetchCharactersAffiliation(array $ids): array
    {
        $affiliations = [];
        while (! empty($ids)) {
            $checkIds = array_splice($ids, 0, 1000);
            try {
                $result = $this->esiApiFactory->getCharacterApi()
                    ->postCharactersAffiliation($checkIds, $this->datasource);
                if (is_array($result)) { // should always be the case here
                    $affiliations = array_merge($affiliations, $result);
                }
            } catch (\Exception $e) {
                $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            }
        }
        return $affiliations;
    }

    /**
     * Updates corporation from ESI.
     *
     * Creates the corporation in the local database if it does not already exist.
     *
     * If the corporation belongs to an alliance this creates a database entity,
     * if it does not already exists, but does not fetch it's data from ESI.
     *
     * Returns null if the ESI requests fails.
     *
     * @param int|null $id EVE corporation ID
     * @param bool $flush Optional write data to database, defaults to true
     * @return null|Corporation An instance that is attached to the Doctrine entity manager.
     */
    public function fetchCorporation(?int $id, bool $flush = true): ?Corporation
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        // get data from ESI
        $this->lastErrorCode = null;
        try {
            $eveCorp = $this->esiApiFactory->getCorporationApi()->getCorporationsCorporationId($id, $this->datasource);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return null;
        }
        if (!$eveCorp instanceof GetCorporationsCorporationIdOk) {
            return null;
        }

        // get or create corp
        $corp = $this->getCorporationEntity($id);

        // update entity
        $corp->setName($eveCorp->getName());
        $corp->setTicker($eveCorp->getTicker());

        try {
            $corp->setLastUpdate(new \DateTime());
        } catch (\Exception $e) {
            // ignore
        }

        // update corporation with alliance entity - does not fetch data from ESI
        $alliId = (int) $eveCorp->getAllianceId();
        if ($alliId > 0) {
            $alliance = $this->getAllianceEntity($alliId);
            $corp->setAlliance($alliance);
            $alliance->addCorporation($corp);
        } else {
            $corp->setAlliance();
        }

        // flush
        if ($flush && ! $this->objectManager->flush()) {
            return null;
        }

        return $corp;
    }

    /**
     * Create/updates alliance.
     *
     * Creates the alliance in the local database if it does not already exist
     * and is a valid alliance.
     *
     * Returns null if the ESI requests fails.
     *
     * @param int|null $id EVE alliance ID
     * @param bool $flush Optional write data to database, defaults to true
     * @return null|Alliance An instance that is attached to the Doctrine entity manager.
     */
    public function fetchAlliance(?int $id, bool $flush = true): ?Alliance
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        // get data from ESI
        $this->lastErrorCode = null;
        try {
            $eveAlli = $this->esiApiFactory->getAllianceApi()->getAlliancesAllianceId($id, $this->datasource);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return null;
        }
        if (!$eveAlli instanceof GetAlliancesAllianceIdOk) {
            return null;
        }

        // get or create alliance
        $alliance = $this->getAllianceEntity($id);

        // update entity
        $alliance->setName($eveAlli->getName());
        $alliance->setTicker($eveAlli->getTicker());

        try {
            $alliance->setLastUpdate(new \DateTime());
        } catch (\Exception $e) {
            // ignore
        }

        // flush
        if ($flush && ! $this->objectManager->flush()) {
            return null;
        }

        return $alliance;
    }

    /**
     * Requests names for IDs from ESI.
     *
     * Note: All IDs need to be valid, but it seems that ESI sometimes complains about an ID that will work later.
     *
     * @param array $ids Valid IDs
     * @return PostUniverseNames200Ok[]
     * @see https://esi.evetech.net/ui/#/Universe/post_universe_names
     */
    public function fetchUniverseNames(array $ids): array
    {
        $names = [];
        while (!empty($ids)) {
            $checkIds = array_splice($ids, 0, 1000);
            try {
                // it's possible that postUniverseNames() returns null
                $result = $this->esiApiFactory->getUniverseApi()->postUniverseNames($checkIds, $this->datasource);
                if (is_array($result)) {
                    $names = array_merge($names, $result);
                }
            } catch (ApiException $e) {
                $context = [Context::EXCEPTION => $e];
                $body = $e->getResponseBody();
                if (
                    $e->getCode() === 404 &&
                    is_string($body) &&
                    strpos($body, 'Ensure all IDs are valid before resolving') !== false
                ) {
                    // Try again with fewer IDs
                    if (count($checkIds) > 100) {
                        $names = $this->fetchUniverseNamesChunked($names, $checkIds, 100);
                    } elseif (count($checkIds) > 10) {
                        $names = $this->fetchUniverseNamesChunked($names, $checkIds, 10);
                    } elseif (count($checkIds) > 1) {
                        $names = $this->fetchUniverseNamesChunked($names, $checkIds, 1);
                    } else {
                        $context['IDs'] = $checkIds;
                        $this->log->error($e->getMessage(), $context);
                    }
                } else {
                    $this->log->error($e->getMessage(), $context);
                }
            } catch (\Exception $e) {
                $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            }
        }
        return $names;
    }

    /**
     * Fetch structure info from ESI and create/update DB entry.
     *
     * Always returns a location object, even if the update failed or was skipped.
     */
    public function fetchStructure(
        int $id,
        string $accessToken,
        bool $increaseErrorCount = true,
        bool $flush = true
    ): EsiLocation {
        $location = $this->repositoryFactory->getEsiLocationRepository()->find($id);
        if ($location === null) {
            $location = new EsiLocation();
            $location->setId($id);
            $location->setCategory(EsiLocation::CATEGORY_STRUCTURE);
            $location->setLastUpdate(new \DateTime());
            $this->objectManager->persist($location);
        }

        if (in_array($id, $this->structuresUpdated)) {
            // No need to flush here
            return $location;
        }
        $this->structuresUpdated[] = $id;

        // Do not continue without a token
        if ($accessToken === '') {
            if ($flush) {
                $this->objectManager->flush();
            }
            return $location;
        }

        // Check error configuration
        if ($this->errorConfiguration === null) {
            $errorVar = $this->repositoryFactory->getSystemVariableRepository()
                ->find(SystemVariable::FETCH_STRUCTURE_NAME_ERROR_DAYS);
            $this->errorConfiguration = $errorVar ? $errorVar->getValue() : '';
        }
        if (!empty($this->errorConfiguration) && $location->getErrorCount() > 0) {
            $configRules = explode(',', $this->errorConfiguration); // value is e.g. 3=7,10=30
            foreach ($configRules as $configRule) {
                $configRuleParts = explode('=', $configRule);
                if (empty($configRuleParts[1])) {
                    continue;
                }
                $configErrorCount = (int) trim($configRuleParts[0]);
                $configErrorDays = (int) trim($configRuleParts[1]);
                $checkDate = new \DateTime("now -$configErrorDays days");
                $locationDate = $location->getLastUpdate() ? $location->getLastUpdate()->getTimestamp() : 0;
                if (
                    $configErrorCount > 0 &&
                    $location->getErrorCount() >= $configErrorCount &&
                    $locationDate > $checkDate->getTimestamp()
                ) {
                    // Note: there's no need to flush here because this cannot be a new location object
                    // and nothing was changed.
                    return $location;
                }
            }
        }

        // Fetch name
        $result = null;
        $authError = false;
        try {
            $result = $this->esiApiFactory->getUniverseApi($accessToken)
                ->getUniverseStructuresStructureId($id, $this->datasource);
        } catch (\Exception $e) {
            if ((int)$e->getCode() === 403) {
                $this->log->info("EsiData::fetchStructure: ". $e->getCode() . " Unauthorized/Forbidden: $id");
                if ($increaseErrorCount) {
                    $authError = true;
                }
            } else {
                $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            }
        }

        // Set result
        if ($result instanceof GetUniverseStructuresStructureIdOk) {
            /** @noinspection PhpCastIsUnnecessaryInspection */
            $location->setName((string) $result->getName());
            /** @noinspection PhpCastIsUnnecessaryInspection */
            $location->setOwnerId((int) $result->getOwnerId());
            /** @noinspection PhpCastIsUnnecessaryInspection */
            $location->setSystemId((int) $result->getSolarSystemId());
            $location->setErrorCount(0);
        } elseif ($authError) {
            $location->setErrorCount($location->getErrorCount() + 1);
        }
        $location->setLastUpdate(new \DateTime());

        // Save and return
        if ($flush) {
            $this->objectManager->flush();
        }
        return $location;
    }

    /**
     * Needs: esi-corporations.read_corporation_membership.v1
     *
     * @return int[] List of character IDs, empty array can also be an ESI error
     */
    public function fetchCorporationMembers(int $id, string $accessToken): array
    {
        if ($accessToken === '') {
            return [];
        }

        try {
            $members = $this->esiApiFactory->getCorporationApi($accessToken)
                ->getCorporationsCorporationIdMembers($id, $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            $members = [];
        }

        return is_array($members) ? $members : [];
    }

    /**
     * Needs: esi-characters.read_corporation_roles.v1
     *
     * @param string[] $roles
     */
    public function verifyRoles(array $roles, int $characterId, string $accessToken): bool
    {
        if (empty($roles)) {
            return true;
        }

        $characterApi = $this->esiApiFactory->getCharacterApi($accessToken);
        try {
            $charRoles = $characterApi->getCharactersCharacterIdRoles($characterId, $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return false;
        }

        if (
            !$charRoles instanceof GetCharactersCharacterIdRolesOk ||
            !is_array($charRoles->getRoles()) ||
            !empty(array_diff($roles, $charRoles->getRoles()))
        ) {
            return false;
        }

        return true;
    }

    public function getCorporationEntity(int $id): Corporation
    {
        $corp = $this->repositoryFactory->getCorporationRepository()->find($id);
        if ($corp === null) {
            $corp = new Corporation();
            $corp->setId($id);
            $this->objectManager->persist($corp);

            // Flush immediately, so that other processes do not try to add it again.
            $this->objectManager->flush();
        }
        return $corp;
    }

    private function getAllianceEntity(int $id): Alliance
    {
        $alliance = $this->repositoryFactory->getAllianceRepository()->find($id);
        if ($alliance === null) {
            $alliance = new Alliance();
            $alliance->setId($id);
            $this->objectManager->persist($alliance);

            // Flush immediately, so that other processes do not try to add it again.
            $this->objectManager->flush();
        }
        return $alliance;
    }

    /**
     * @return PostUniverseNames200Ok[]
     */
    private function fetchUniverseNamesChunked(array $names, array $checkIds, int $chunkSize): array
    {
        $this->log->warning("fetchUniverseNames: Invalid ID(s) in request, trying again with max. $chunkSize IDs.");
        $chunkSize = max(1, min($chunkSize, PHP_INT_MAX));
        foreach (array_chunk($checkIds, $chunkSize) as $chunks) {
            $names = array_merge($names, $this->fetchUniverseNames($chunks));
        }
        return $names;
    }
}
