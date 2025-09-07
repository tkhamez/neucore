<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\SystemVariable;
use Neucore\Exception\Exception;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\Context;
use Neucore\Service\Character as CharacterService;
use Psr\Log\LoggerInterface;
use Tkhamez\Eve\API\ApiException;
use Tkhamez\Eve\API\Model\AlliancesAllianceIdGet;
use Tkhamez\Eve\API\Model\CharactersAffiliationPostInner;
use Tkhamez\Eve\API\Model\CharactersCharacterIdGet;
use Tkhamez\Eve\API\Model\CharactersCharacterIdRolesGet;
use Tkhamez\Eve\API\Model\CorporationsCorporationIdGet;
use Tkhamez\Eve\API\Model\Error;
use Tkhamez\Eve\API\Model\UniverseNamesPostInner;
use Tkhamez\Eve\API\Model\UniverseStructuresStructureIdGet;

/**
 * Fetch and process data from ESI.
 */
class EsiData
{
    public const CORPORATION_DOOMHEIM_ID = 1000001;

    private ?int $lastErrorCode = null;

    /**
     * @var int[]
     */
    private array $structuresUpdated = [];

    /**
     * Cache of SystemVariable::FETCH_STRUCTURE_ERROR_DAYS
     */
    private ?string $errorConfiguration = null;

    public function __construct(
        private readonly LoggerInterface   $log,
        private readonly EsiApiFactory     $esiApiFactory,
        private readonly ObjectManager     $objectManager,
        private readonly RepositoryFactory $repositoryFactory,
        private readonly CharacterService  $characterService,
    ) {
    }

    public function getLastErrorCode(): ?int
    {
        return $this->lastErrorCode;
    }

    /**
     * Fetch character from ESI.
     *
     * @throws Exception If the character was deleted, not found or any other ESI error.
     */
    public function fetchCharacter(int $id): CharactersCharacterIdGet
    {
        $characterApi = $this->esiApiFactory->getCharacterApi();
        try {
            $eveChar = $characterApi->getCharactersCharacterId($id);
        } catch (ApiException $e) {
            $body = $e->getResponseBody();
            // The 404 checks are probably no longer necessary when this is merged:
            // https://github.com/OpenAPITools/openapi-generator/pull/19483
            if (
                $e->getCode() === 404 &&
                is_string($body) &&
                str_contains($body, 'Character not found')
            ) {
                throw new Exception('Character not found (exception)', 404);
            } elseif (
                $e->getCode() === 404 &&
                is_string($body) &&
                str_contains($body, 'Character has been deleted')
            ) {
                throw new Exception('Character has been deleted (exception)', 410);
            } {
                throw new Exception(is_string($body) ? $body : $e->getMessage());
            }
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
            throw new Exception('ESI error.');
        }

        if ($eveChar instanceof Error) {
            /** @noinspection PhpCastIsUnnecessaryInspection */
            $error = (string) $eveChar->getError();
            if (str_contains($error, 'Character not found')) {
                throw new Exception('Character not found (error object)', 404);
            } elseif (str_contains($error, 'Character has been deleted')) {
                throw new Exception('Character has been deleted (error object)', 410);
            } else {
                throw new Exception($error);
            }
        }

        return $eveChar;
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
        $char = $this->updateCharacter($id, false);
        // corp is never null here, but that's not obvious
        if ($char === null || $char->getCorporation() === null) {
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

        if (!$this->objectManager->flush2()) {
            return null;
        }

        return $char;
    }

    /**
     * Updates character from ESI.
     *
     * The character must already exist.
     *
     * If the character's corporation is not yet in the database, it will
     * be created but not updated with data from ESI.
     *
     * Returns null if the ESI requests fails or if the character
     * does not exist in the local database.
     *
     * @param int|null $id
     * @param bool $flush Optional write data to a database, defaults to true
     * @return null|Character An instance that is attached to the Doctrine entity manager.
     */
    public function updateCharacter(?int $id, bool $flush = true): ?Character
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        // get char from the local database
        $char = $this->repositoryFactory->getCharacterRepository()->find($id);
        if ($char === null) {
            return null;
        }

        // Get data from character
        $this->lastErrorCode = null;
        $corpId = null;
        $eveChar = null;
        try {
            // ESI cache = 24 hours.
            // But maybe faster than /characters/affiliation/ if the character was deleted.
            $eveChar = $this->fetchCharacter($id);
        } catch (Exception $e) {
            $this->lastErrorCode = $e->getCode();
            if ($e->getCode() === 410) {
                $corpId = self::CORPORATION_DOOMHEIM_ID;
            } else {
                return null;
            }
        }

        $updated = false;

        // update char (and player) name
        if ($eveChar instanceof CharactersCharacterIdGet) {
            /** @noinspection PhpCastIsUnnecessaryInspection */
            $this->characterService->setCharacterName($char, (string) $eveChar->getName());
            if ($char->getMain()) {
                $char->getPlayer()->setName($char->getName());
            }
            $updated = true;
        }

        // Update char with corp entity - ESI cache = 1 hour.
        // But maybe slower than /characters/{character_id}/ if the character was deleted.
        if (!$corpId) {
            $affiliation = $this->fetchCharactersAffiliation([$id]);
            if (isset($affiliation[0])) {
                /** @noinspection PhpCastIsUnnecessaryInspection */
                $corpId = (int) $affiliation[0]->getCorporationId();
            } elseif ($eveChar instanceof CharactersCharacterIdGet) {
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
            } catch (\Exception) {
                // ignore
            }
        }

        // flush
        if ($flush && !$this->objectManager->flush2()) {
            return null;
        }

        return $char;
    }

    /**
     * @param array $ids Valid IDs
     * @return CharactersAffiliationPostInner[]
     * @see https://developers.eveonline.com/api-explorer#/operations/PostCharactersAffiliation
     */
    public function fetchCharactersAffiliation(array $ids): array
    {
        $characterApi = $this->esiApiFactory->getCharacterApi();
        $affiliations = [];
        while (!empty($ids)) {
            $checkIds = array_splice($ids, 0, 1000);
            try {
                $result = $characterApi->postCharactersAffiliation($checkIds);
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
     * If the corporation belongs to an alliance, this creates a database entity
     * if it does not already exist but does not fetch its data from ESI.
     *
     * Returns null if the ESI requests fails.
     *
     * @param int|null $id EVE corporation ID
     * @param bool $flush Optional write data to the database, defaults to true
     * @return null|Corporation An instance that is attached to the Doctrine entity manager.
     */
    public function fetchCorporation(?int $id, bool $flush = true): ?Corporation
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        // get data from ESI
        $corporationApi = $this->esiApiFactory->getCorporationApi();
        $this->lastErrorCode = null;
        try {
            $eveCorp = $corporationApi->getCorporationsCorporationId($id);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return null;
        }
        if (!$eveCorp instanceof CorporationsCorporationIdGet) {
            return null;
        }

        // get or create corp
        $corp = $this->getCorporationEntity($id);

        // update entity
        $corp->setName($eveCorp->getName());
        $corp->setTicker($eveCorp->getTicker());

        try {
            $corp->setLastUpdate(new \DateTime());
        } catch (\Exception) {
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
        if ($flush && !$this->objectManager->flush2()) {
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
     * @param bool $flush Optional write data to the database, defaults to true
     * @return null|Alliance An instance that is attached to the Doctrine entity manager.
     */
    public function fetchAlliance(?int $id, bool $flush = true): ?Alliance
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        // get data from ESI
        $allianceApi = $this->esiApiFactory->getAllianceApi();
        $this->lastErrorCode = null;
        try {
            $eveAlli = $allianceApi->getAlliancesAllianceId($id);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return null;
        }
        if (!$eveAlli instanceof AlliancesAllianceIdGet) {
            return null;
        }

        // get or create alliance
        $alliance = $this->getAllianceEntity($id);

        // update entity
        $alliance->setName($eveAlli->getName());
        $alliance->setTicker($eveAlli->getTicker());

        try {
            $alliance->setLastUpdate(new \DateTime());
        } catch (\Exception) {
            // ignore
        }

        // flush
        if ($flush && !$this->objectManager->flush2()) {
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
     * @return UniverseNamesPostInner[]
     * @see https://developers.eveonline.com/api-explorer#/operations/PostUniverseNames
     */
    public function fetchUniverseNames(array $ids, int $maxItems = 1000): array
    {
        $universeApi = $this->esiApiFactory->getUniverseApi();
        $names = [];
        while (!empty($ids)) {
            $checkIds = array_splice($ids, 0, $maxItems);
            try {
                // it's possible that postUniverseNames() returns null
                $result = $universeApi->postUniverseNames($checkIds);
                if (is_array($result)) {
                    $names = array_merge($names, $result);
                }
            } catch (ApiException $e) {
                $context = [Context::EXCEPTION => $e];
                $body = $e->getResponseBody();
                // This will probably change when this is merged:
                // https://github.com/OpenAPITools/openapi-generator/pull/19483,
                // see also fetchCharacter().
                if (
                    $e->getCode() === 404 &&
                    is_string($body) &&
                    str_contains($body, 'Ensure all IDs are valid before resolving')
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
        bool $flush = true,
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
        $universeApi = $this->esiApiFactory->getUniverseApi($accessToken);
        try {
            $result = $universeApi->getUniverseStructuresStructureId($id);
        } catch (\Exception $e) {
            if ((int) $e->getCode() === 403) {
                $this->log->info("EsiData::fetchStructure: " . $e->getCode() . " Unauthorized/Forbidden: $id");
                if ($increaseErrorCount) {
                    $authError = true;
                }
            } else {
                $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            }
        }

        // Set result
        if ($result instanceof UniverseStructuresStructureIdGet) {
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
     * @return int[] List of character IDs, an empty array can also be an ESI error
     */
    public function fetchCorporationMembers(int $id, string $accessToken): array
    {
        if ($accessToken === '') {
            return [];
        }

        $corporationApi = $this->esiApiFactory->getCorporationApi($accessToken);
        try {
            $members = $corporationApi->getCorporationsCorporationIdMembers($id);
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
            $charRoles = $characterApi->getCharactersCharacterIdRoles($characterId);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return false;
        }

        if (
            !$charRoles instanceof CharactersCharacterIdRolesGet ||
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

            // Flush immediately so that other processes do not try to add it again.
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

            // Flush immediately so that other processes do not try to add it again.
            $this->objectManager->flush();
        }
        return $alliance;
    }

    /**
     * @return UniverseNamesPostInner[]
     */
    private function fetchUniverseNamesChunked(array $names, array $checkIds, int $chunkSize): array
    {
        $this->log->warning(
            "fetchUniverseNames: Invalid ID(s) in request, trying again with max. $chunkSize IDs.",
        );
        $chunkSize = max(1, min($chunkSize, PHP_INT_MAX));
        foreach (array_chunk($checkIds, $chunkSize) as $chunks) {
            $names = array_merge($names, $this->fetchUniverseNames($chunks));
        }
        return $names;
    }
}
