<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\Character;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiType;
use Neucore\Entity\Player;

/**
 * @method CorporationMember|null find($id, $lockMode = null, $lockVersion = null)
 * @method CorporationMember[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CorporationMemberRepository extends EntityRepository
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * @var int|null
     */
    private $active;

    /**
     * @var int|null
     */
    private $inactive;

    /**
     * @var bool|null
     */
    private $account;

    /**
     * @var bool|null
     */
    private $validToken;

    /**
     * @var int|null
     */
    private $tokenChanged;

    /**
     * Limit to members who were active in the last x days.
     */
    public function setActive(?int $days): self
    {
        $this->active = $days;

        return $this;
    }

    /**
     * Limit to members who have been inactive for x days or longer.
     */
    public function setInactive(?int $days): self
    {
        $this->inactive = $days;

        return $this;
    }

    /**
     * Limit to members with (true) or without (false) an account
     */
    public function setAccount(?bool $account): self
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Limit to characters with a valid (true) or invalid (false) token
     */
    public function setValidToken(?bool $validToken): self
    {
        $this->validToken = $validToken;

        return $this;
    }

    /**
     * Limit to characters whose ESI token status has not changed for x days
     */
    public function setTokenChanged(?int $days): self
    {
        $this->tokenChanged = $days;

        return $this;
    }

    /**
     * Reset filter variables.
     */
    public function resetCriteria(): self
    {
        $this->setInactive(null);
        $this->setActive(null);
        $this->setAccount(null);
        $this->setValidToken(null);
        $this->setTokenChanged(null);

        return $this;
    }

    /**
     * Find members.
     *
     * This sets only objects and properties that are used in jsonSerialize(),
     * for example the corporation is not included.
     *
     * @param int $corporationId EVE corporation ID
     * @return CorporationMember[] The returned entities are *not* attached to the entity manager.
     */
    public function findMatching(int $corporationId): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.character', 'c')
            ->leftJoin('m.location', 'l')
            ->leftJoin('m.shipType', 's')
            ->leftJoin('c.player', 'p')
            ->select(
                'm.id',
                'm.name',
                'm.logoffDate',
                'm.logonDate',
                'm.startDate',
                'l.id AS locationId',
                'l.name AS locationName',
                'l.category AS locationCategory',
                's.id AS shipId',
                's.name AS shipName',
                'c.id AS characterId',
                'c.name AS characterName',
                'c.main',
                'c.lastUpdate',
                'c.validToken',
                'c.validTokenTime',
                'p.id AS playerId',
                'p.name AS playerName'
            )
            ->where('m.corporation = :corporation_id')->setParameter('corporation_id', $corporationId)
            ->orderBy('m.logonDate', 'DESC');

        if ($this->active > 0 && ($activeDate = date_create('now -'.$this->active.' days'))) {
            $qb->andWhere('m.logonDate >= :active')->setParameter('active', $activeDate->format(self::DATE_FORMAT));
        }
        if ($this->inactive > 0 && ($inactiveDate = date_create('now -'.$this->inactive.' days'))) {
            $qb->andWhere('m.logonDate < :inactive')
                ->setParameter('inactive', $inactiveDate->format(self::DATE_FORMAT));
        }
        if ($this->account) {
            $qb->andWhere($qb->expr()->isNotNull('m.character'));
        } elseif ($this->account === false) {
            $qb->andWhere($qb->expr()->isNull('m.character'));
        }
        if ($this->validToken !== null || $this->tokenChanged > 0) {
            $qb->andWhere('c.id IS NOT NULL');
        }
        if ($this->validToken) {
            $qb->andWhere($qb->expr()->eq('c.validToken', 1));
        } elseif ($this->validToken === false) {
            $qb->andWhere($qb->expr()->eq('c.validToken', 0));
        }
        if ($this->tokenChanged > 0 && ($tokenChangedDate = date_create('now -'.$this->tokenChanged.' days'))) {
            $qb->andWhere('c.validTokenTime < :tokenChanged')
                ->setParameter('tokenChanged', $tokenChangedDate->format(self::DATE_FORMAT));
        }

        $result = $qb->getQuery()->getResult();

        return array_map(function ($r) {
            $member = (new CorporationMember())
                ->setId((int) $r['id'])
                ->setName($r['name'])
            ;
            if ($r['logoffDate']) {
                $member->setLogoffDate($r['logoffDate']);
            }
            if ($r['logonDate']) {
                $member->setLogonDate($r['logonDate']);
            }
            if ($r['startDate']) {
                $member->setStartDate($r['startDate']);
            }

            if ($r['locationId']) {
                $location = (new EsiLocation())
                    ->setId((int) $r['locationId'])
                    ->setName((string) $r['locationName'])
                    ->setCategory($r['locationCategory']);
                $member->setLocation($location);
            }

            if ($r['shipId']) {
                $ship = (new EsiType())
                    ->setId((int) $r['shipId'])
                    ->setName((string) $r['shipName']);
                $member->setShipType($ship);
            }

            if ($r['characterId']) {
                $character = (new Character())
                    ->setId((int) $r['characterId'])
                    ->setName($r['characterName'])
                    ->setMain((bool) $r['main'])
                    ->setValidToken($r['validToken'] !== null ? (bool) $r['validToken'] : null);
                if ($r['lastUpdate']) {
                    $character->setLastUpdate($r['lastUpdate']);
                }
                if ($r['validTokenTime']) {
                    $character->setValidTokenTime($r['validTokenTime']);
                }
                $member->setCharacter($character);

                if ($r['playerId']) {
                    $player = (new Player())
                        ->setId((int) $r['playerId'])
                        ->setName($r['playerName']);
                    $character->setPlayer($player);
                }
            }

            return $member;
        }, $result);
    }

    public function removeFormerMembers(int $corporationId, array $currentMemberIds): int
    {
        $qb = $this->createQueryBuilder('m');
        $qb->delete()
            ->where('m.corporation = :corpId')
            ->andWhere($qb->expr()->notIn('m.id', ':ids'))
            ->setParameter('corpId', $corporationId)
            ->setParameter('ids', $currentMemberIds);
        return $qb->getQuery()->getResult();
    }

    /**
     * Returns members from corporations without an account that logged in during the last x days.
     *
     * @param int[] $corporationIds
     * @param int $loginDays days since last login, minimum = 1
     * @param int|null $dbResultLimit
     * @param int $offset
     * @return CorporationMember[]
     */
    public function findByCorporationsWithoutAccountAndActive(
        array $corporationIds,
        int $loginDays,
        $dbResultLimit = null,
        $offset = 0
    ): array {
        if (count($corporationIds) === 0) {
            return [];
        }
        if ($loginDays < 1) {
            return [];
        }

        $minLoginDate = date_create('now -'.$loginDays.' days');
        if (! $minLoginDate) {
            return [];
        }

        $qb = $this->createQueryBuilder('m');
        $qb->where($qb->expr()->in('m.corporation', ':corporationIds'))
            ->andWhere($qb->expr()->isNull('m.character'))
            ->setParameter('corporationIds', $corporationIds)
            ->andWhere('m.logonDate > :minLoginDate')
            ->setParameter('minLoginDate', $minLoginDate->format(self::DATE_FORMAT))
            ->orderBy('m.name')
            ->setMaxResults($dbResultLimit) // don't use with JOIN
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    public function fetchCorporationIds()
    {
        $corporations = $this->createQueryBuilder('m')
            ->leftJoin('m.corporation', 'c')
            ->select('c.id')
            ->groupBy('m.corporation')
            ->getQuery()
            ->getResult();

        return array_map(function ($corporation) {
            return $corporation['id'];
        }, $corporations);
    }
}
