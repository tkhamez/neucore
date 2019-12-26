<?php declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\EsiLocation;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Model\GetUniverseStructuresStructureIdOk;
use Swagger\Client\Eve\Model\PostCharactersAffiliation200Ok;
use Swagger\Client\Eve\Model\PostUniverseNames200Ok;

/**
 * Fetch and process data from ESI.
 */
class EsiData
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
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var int|null
     */
    private $lastErrorCode;

    /**
     * @var string
     */
    private $datasource;

    public function __construct(
        LoggerInterface $log,
        EsiApiFactory $esiApiFactory,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        Config $config
    ) {
        $this->log = $log;
        $this->esiApiFactory = $esiApiFactory;
        $this->objectManager = $objectManager;
        $this->repositoryFactory = $repositoryFactory;

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
     * @return NULL|Character
     */
    public function fetchCharacterWithCorporationAndAlliance(?int $id)
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
    public function fetchCharacter(?int $id, bool $flush = true)
    {
        if ($id === null || $id <= 0) {
            return null;
        }

        // get char from local database
        $char = $this->repositoryFactory->getCharacterRepository()->find($id);
        if ($char === null) {
            return null;
        }

        // get data from ESI
        $this->lastErrorCode = null;
        try {
            // cache = 24 hours
            $eveChar = $this->esiApiFactory->getCharacterApi()->getCharactersCharacterId($id, $this->datasource);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return null;
        }

        // update char (and player) name
        $char->setName($eveChar->getName());
        if ($char->getMain()) {
            $char->getPlayer()->setName($char->getName());
        }

        try {
            $char->setLastUpdate(new \DateTime());
        } catch (\Exception $e) {
            // ignore
        }

        // update char with corp entity - does not fetch data from ESI
        $affiliation = $this->fetchCharactersAffiliation([$id]); // cache = 1 hour
        if (isset($affiliation[0])) {
            $corpId = (int) $affiliation[0]->getCorporationId();
        } else {
            $corpId = (int) $eveChar->getCorporationId();
        }
        $corp = $this->getCorporationEntity($corpId);
        $char->setCorporation($corp);
        $corp->addCharacter($char);

        // flush
        if ($flush && ! $this->objectManager->flush()) {
            return null;
        }

        return $char;
    }

    /**
     * @param array $ids Valid IDs
     * @return PostCharactersAffiliation200Ok[]
     * @see https://esi.evetech.net/ui/#/Character/post_characters_affiliation
     */
    public function fetchCharactersAffiliation(array $ids)
    {
        $affiliations = [];
        while (count($ids) > 0) {
            $checkIds = array_splice($ids, 0, 1000);
            try {
                $result = $this->esiApiFactory->getCharacterApi()
                    ->postCharactersAffiliation($checkIds, $this->datasource);
                if (is_array($result)) { // should always be the case here
                    $affiliations = array_merge($affiliations, $result);
                }
            } catch (\Exception $e) {
                $this->log->error($e->getMessage(), ['exception' => $e]);
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
    public function fetchCorporation(?int $id, bool $flush = true)
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
            $this->log->error($e->getMessage(), ['exception' => $e]);
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
            $corp->setAlliance(null);
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
    public function fetchAlliance(?int $id, bool $flush = true)
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
            $this->log->error($e->getMessage(), ['exception' => $e]);
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
     * @param array $ids Valid IDs
     * @return PostUniverseNames200Ok[]
     * @see https://esi.evetech.net/ui/#/Universe/post_universe_names
     */
    public function fetchUniverseNames(array $ids): array
    {
        $names = [];
        while (count($ids) > 0) {
            $checkIds = array_splice($ids, 0, 1000);
            try {
                // it's possible that postUniverseNames() returns null
                $result = $this->esiApiFactory->getUniverseApi()->postUniverseNames($checkIds, $this->datasource);
                if (is_array($result)) {
                    $names = array_merge($names, $result);
                }
            } catch (\Exception $e) {
                $this->log->error($e->getMessage(), ['exception' => $e]);
            }
        }
        return $names;
    }

    /**
     * Fetch structure info from ESI and create/update DB entry on success.
     */
    public function fetchStructure(int $id, string $accessToken, bool $flush = true): ?EsiLocation
    {
        if ($accessToken === '') {
            return null;
        }

        try {
            $result = $this->esiApiFactory->getUniverseApi($accessToken)
                ->getUniverseStructuresStructureId($id, $this->datasource);
        } catch (\Exception $e) {
            if (in_array($e->getCode(), [401, 403])) {
                $this->log->info("EsiData::fetchStructure: ". $e->getCode() . " Unauthorized/Forbidden: $id");
            } else {
                $this->log->error($e->getMessage(), ['exception' => $e]);
            }
            return null;
        }

        $location = null;
        if ($result instanceof GetUniverseStructuresStructureIdOk) {
            $location = $this->repositoryFactory->getEsiLocationRepository()->find($id);
            if ($location === null) {
                $location = new EsiLocation();
                $location->setId($id);
                $location->setCategory(EsiLocation::CATEGORY_STRUCTURE);
                $this->objectManager->persist($location);
            }
            $location->setName((string) $result->getName());
            $location->setOwnerId((int) $result->getOwnerId());
            $location->setSystemId((int) $result->getSolarSystemId());
            $location->setLastUpdate(new \DateTime());

            if ($flush) {
                $this->objectManager->flush();
            }
        }

        return $location;
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
}
