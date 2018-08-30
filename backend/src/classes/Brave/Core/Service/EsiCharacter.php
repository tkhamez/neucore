<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Alliance;
use Brave\Core\Repository\AllianceRepository;
use Brave\Core\Repository\CharacterRepository;
use Brave\Core\Repository\CorporationRepository;
use Brave\Core\Entity\Corporation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class EsiCharacter
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var EsiApi
     */
    private $esi;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var AllianceRepository
     */
    private $alliRepo;

    /**
     * @var CorporationRepository
     */
    private $corpRepo;

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    public function __construct(
        LoggerInterface $log,
        EsiApi $esi,
        EntityManagerInterface $em,
        AllianceRepository $ar,
        CorporationRepository $cpr,
        CharacterRepository $crr
    ) {
        $this->log = $log;
        $this->esi = $esi;
        $this->em = $em;
        $this->alliRepo = $ar;
        $this->corpRepo = $cpr;
        $this->charRepo = $crr;
    }

    /**
     *
     * @return \Brave\Core\Service\EsiApi
     */
    public function getEsiApi()
    {
        return $this->esi;
    }

    /**
     * Updates character, corporation and alliance from ESI.
     *
     * Character must already exist in the local database.
     * Returns null if any of the ESI requests fails.
     *
     * @param int $id EVE character ID
     * @return NULL|\Brave\Core\Entity\Character
     */
    public function fetchCharacterWithCorporationAndAlliance(int $id)
    {
        $char = $this->fetchCharacter($id, false);
        if ($char === null) {
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

        if (! $this->flush()) {
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
     * @param int $id
     * @param bool $flush Optional write data to database, defaults to true
     * @return null|\Brave\Core\Entity\Character An instance that is attached to the Doctrine EntityManager.
     */
    public function fetchCharacter(int $id, bool $flush = true)
    {
        if ($id <= 0) {
            return null;
        }

        // get char from local database
        $char = $this->charRepo->find($id);
        if ($char === null) {
            return null;
        }

        // get data from ESI
        $eveChar = $this->esi->getCharacter($id);
        if ($eveChar === null) {
            return null;
        }
        $char->setName($eveChar->getName());
        $char->setLastUpdate(new \DateTime());

        // update char with corp entity - does not fetch data from ESI
        $corpId = (int) $eveChar->getCorporationId();
        $corp = $this->getCorporationEntity($corpId);
        $char->setCorporation($corp);
        $corp->addCharacter($char);

        // flush
        if ($flush && ! $this->flush()) {
            return null;
        }

        return $char;
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
     * @param int $id EVE corporation ID
     * @param bool $flush Optional write data to database, defaults to true
     * @return null|\Brave\Core\Entity\Corporation An instance that is attached to the Doctrine EntityManager.
     */
    public function fetchCorporation(int $id, bool $flush = true)
    {
        if ($id <= 0) {
            return null;
        }

        // get data from ESI
        $eveCorp = $this->esi->getCorporation($id);
        if ($eveCorp === null) {
            return null;
        }

        // get or create corp
        $corp = $this->getCorporationEntity($id);

        // update entity
        $corp->setName($eveCorp->getName());
        $corp->setTicker($eveCorp->getTicker());
        $corp->setLastUpdate(new \DateTime());

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
        if ($flush && ! $this->flush()) {
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
     * @param int $id EVE alliance ID
     * @param bool $flush Optional write data to database, defaults to true
     * @return null|\Brave\Core\Entity\Alliance An instance that is attached to the Doctrine EntityManager.
     */
    public function fetchAlliance(int $id, bool $flush = true)
    {
        if ($id <= 0) {
            return null;
        }

        // get data from ESI
        $eveAlli = $this->esi->getAlliance($id);
        if ($eveAlli === null) {
            return null;
        }

        // get or create alliance
        $alliance = $this->getAllianceEntity($id);

        // update entity
        $alliance->setName($eveAlli->getName());
        $alliance->setTicker($eveAlli->getTicker());
        $alliance->setLastUpdate(new \DateTime());

        // flush
        if ($flush && ! $this->flush()) {
            return null;
        }

        return $alliance;
    }

    private function getCorporationEntity(int $id): Corporation
    {
        $corp = $this->corpRepo->find($id);
        if ($corp === null) {
            $corp = new Corporation();
            $corp->setId($id);
            $this->em->persist($corp);
        }
        return $corp;
    }

    private function getAllianceEntity(int $id): Alliance
    {
        $alliance = $this->alliRepo->find($id);
        if ($alliance === null) {
            $alliance = new Alliance();
            $alliance->setId($id);
            $this->em->persist($alliance);
        }
        return $alliance;
    }

    private function flush(): bool
    {
        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return false;
        }
        return true;
    }
}
