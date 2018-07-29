<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\AllianceRepository;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\CorporationRepository;
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
     * Updates character from ESI.
     *
     * The character must already exist.
     *
     * If the character's corporation is not yet in the database it will
     * be created, it will optionally also be updated from ESI. Same for alliance.
     *
     * Returns null if any of the ESI requests fails or if the character
     * does not exist in the local database.
     *
     * @param int $id
     * @param bool $updateCorp Optionally update corporation from ESI, defaults to true
     * @return null|\Brave\Core\Entity\Character An instance that is attached to the Doctrine EntityManager.
     */
    public function fetchCharacter(int $id, bool $updateCorp = true)
    {
        if ($id <= 0) {
            return null;
        }

        // get char from local database
        $char = $this->charRepo->find($id);
        if ($char === null) {
            return null;
        }

        // get char from ESI
        $eveChar = $this->esi->getCharacter($id);
        if ($eveChar === null) {
            return null;
        }
        $char->setName($eveChar->getName());
        $char->setLastUpdate(new \DateTime());

        // create corporation
        $corpId = (int) $eveChar->getCorporationId();
        $corp = $this->fetchCorporation($corpId, $updateCorp, false);
        if ($corp === null) {
            return null;
        }
        $char->setCorporation($corp);
        $corp->addCharacter($char);

        // flush
        if (! $this->flush()) {
            var_dump(12313123123);
            return null;
        }

        return $char;
    }

    /**
     * Create and/or update corporation.
     *
     * Creates the corporation in the local database if it does not already exist.
     * Same for alliance if the update parameter is true or if it's a new corp.
     *
     * Returns null if any of the ESI requests fails.
     *
     * @param int $id
     * @param bool $update Optionally update from ESI (including alliance), defaults to true
     * @param bool $flush Optional write data to database, defaults to true
     * @return null|\Brave\Core\Entity\Corporation An instance that is attached to the Doctrine EntityManager.
     */
    public function fetchCorporation(int $id, bool $update = true, bool $flush = true)
    {
        if ($id <= 0) {
            return null;
        }

        // get or create corp
        $corp = $this->corpRepo->find($id);
        if ($corp === null) {
            $corp = new Corporation();
            $corp->setId($id);
            $update = true; // check if corp exists
        }

        if ($update) {
            // update corp
            $eveCorp = $this->esi->getCorporation($id);
            if ($eveCorp === null) {
                return null;
            }
            $corp->setName($eveCorp->getName());
            $corp->setTicker($eveCorp->getTicker());
            $corp->setLastUpdate(new \DateTime());

            // create/fetch alliance
            $alliId = (int) $eveCorp->getAllianceId();
            if ($alliId > 0) {
                $alliance = $this->fetchAlliance($alliId, true, false);
                if ($alliance === null) {
                    return null;
                }
                $corp->setAlliance($alliance);
                $alliance->addCorporation($corp);
            } else {
                $corp->setAlliance(null);
            }
        }

        // persist new corp
        $this->em->persist($corp);

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
     * @param int $id
     * @param bool $update Optionally update from ESI, defaults to true
     * @param bool $flush Optional write data to database, defaults to true
     * @return null|\Brave\Core\Entity\Alliance An instance that is attached to the Doctrine EntityManager.
     */
    public function fetchAlliance(int $id, bool $update = true, bool $flush = true)
    {
        if ($id <= 0) {
            return null;
        }

        // get or create alliance
        $alliance = $this->alliRepo->find($id);
        if ($alliance === null) {
            $alliance = new Alliance();
            $alliance->setId($id);
            $update = true;
        }

        // update from ESI
        if ($update) {
            $eveAlli = $this->esi->getAlliance($id);
            if ($eveAlli === null) {
                return null;
            }
            $alliance->setName($eveAlli->getName());
            $alliance->setTicker($eveAlli->getTicker());
            $alliance->setLastUpdate(new \DateTime());
        }

        // persist new alliance
        $this->em->persist($alliance);

        // flush
        if ($flush && ! $this->flush()) {
            return null;
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
