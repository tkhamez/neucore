<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\AllianceRepository;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\CorporationRepository;
use Brave\Core\Entity\Corporation;
use Doctrine\ORM\EntityManagerInterface;

class CharacterService
{
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

    public function __construct(EsiApi $esi, EntityManagerInterface $em,
        AllianceRepository $ar, CorporationRepository $cpr, CharacterRepository $crr)
    {
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
     * Also fetches the corporation and alliance if there is one.
     *
     * Returns null if any of the ESI requests fails.
     *
     * @param int $id
     * @param bool $flush Flush changes to now to the database.
     * @return null|\Brave\Core\Entity\Character
     */
    public function fetchCharacter(int $id, bool $flush = false)
    {
        if ($id <= 0) {
            return;
        }

        $eveChar = $this->esi->getCharacter($id);
        if ($eveChar === null) {
            return null;
        }

        $char = $this->charRepo->find($id);
        if ($char === null) {
            return null;
        }
        $char->setName($eveChar->getName());
        $char->setLastUpdate(new \DateTime());

        // fetch corp (with alliance)
        $corpId = (int) $eveChar->getCorporationId();
        $corp = $this->fetchCorporation($corpId);
        if ($corp === null) {
            return null;
        }
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
     * Also fetches the alliance if there is one.
     *
     * Returns null if any of the ESI requests fails.
     *
     * @param int $id
     * @param bool $flush Flush changes to now to the database.
     * @return null|\Brave\Core\Entity\Corporation
     */
    public function fetchCorporation(int $id, bool $flush = false)
    {
        if ($id <= 0) {
            return;
        }

        $eveCorp = $this->esi->getCorporation($id);
        if ($eveCorp === null) {
            return null;
        }

        // create/update corp
        $corp = $this->corpRepo->find($id);
        if ($corp === null) {
            $corp = new Corporation();
            $corp->setId($id);
            $this->em->persist($corp);
        }
        $corp->setName($eveCorp->getName());
        $corp->setTicker($eveCorp->getTicker());

        // fetch alliance
        $alliId = (int) $eveCorp->getAllianceId();
        if ($alliId > 0) {
            $alliance = $this->fetchAlliance($alliId);
            if ($alliance === null) {
                return null;
            }
            $corp->setAlliance($alliance);
            $alliance->addCorporation($corp);
        }

        // flush
        if ($flush && ! $this->flush()) {
            return null;
        }

        return $corp;
    }

    /**
     * Updates alliance from ESI.
     *
     * Creates the alliance in the local database if it does not already exist.
     *
     * @param int $id
     * @param bool $flush Flush changes to now to the database.
     * @return null|\Brave\Core\Entity\Alliance
     */
    public function fetchAlliance(int $id, bool $flush = false)
    {
        if ($id <= 0) {
            return;
        }

        $eveAlli = $this->esi->getAlliance($id);
        if ($eveAlli === null) {
            return null;
        }

        $alliance = $this->alliRepo->find($id);
        if ($alliance === null) {
            $alliance = new Alliance();
            $alliance->setId($id);
            $this->em->persist($alliance);
        }
        $alliance->setName($eveAlli->getName());
        $alliance->setTicker($eveAlli->getTicker());

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
