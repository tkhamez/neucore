<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\Character;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiLocation;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EsiType;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Player;

/**
 * @method CorporationMember|null find($id, $lockMode = null, $lockVersion = null)
 * @method CorporationMember[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CorporationMemberRepository extends EntityRepository
{
    public const TOKEN_STATUS_VALID = 1;

    public const TOKEN_STATUS_INVALID = 2;

    public const TOKEN_STATUS_NONE = 3;

    private const NOW = 'now';

    private const DAYS = 'days';

    private const DATE_FORMAT = 'Y-m-d H:i:s';

    private ?int $active = null;

    private ?int $inactive = null;

    private ?bool $account = null;

    private ?int $tokenStatus = null;

    private ?int $tokenChanged = null;

    private ?int $mailCount = null;

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
    public function setTokenStatus(?int $tokenStatus): self
    {
        $this->tokenStatus = $tokenStatus;

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
     * Limit to characters whose "missing player" mail count is greater than or equal to x.
     */
    public function setMailCount(?int $count): self
    {
        $this->mailCount = $count;

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
        $this->setTokenStatus(null);
        $this->setTokenChanged(null);
        $this->setMailCount(null);

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
            ->leftJoin('Neucore\Entity\Character', 'c', 'WITH', 'm.id = c.id') // join via primary IDs
            ->leftJoin('m.location', 'l')
            ->leftJoin('m.shipType', 's')
            ->leftJoin('c.player', 'p')
            ->leftJoin('Neucore\Entity\EveLogin', 'el', 'WITH', 'el.name = :loginName')
            ->leftJoin('el.esiTokens', 'e', 'WITH', 'e.character = c.id')
            ->select([
                'm.id',
                'm.name',
                'm.logoffDate',
                'm.logonDate',
                'm.startDate',
                'm.missingCharacterMailSentDate',
                'm.missingCharacterMailSentResult',
                'm.missingCharacterMailSentNumber',
                'l.id AS locationId',
                'l.name AS locationName',
                'l.category AS locationCategory',
                's.id AS shipId',
                's.name AS shipName',
                'c.id AS characterId',
                'c.name AS characterName',
                'c.main',
                'c.created',
                'c.lastUpdate',
                'e.validToken',
                'e.validTokenTime',
                'e.lastChecked',
                'p.id AS playerId',
                'p.name AS playerName'
            ])
            ->where('m.corporation = :corporation_id')
            ->orderBy('m.logonDate', 'DESC')
            ->setParameter('loginName', EveLogin::NAME_DEFAULT)
            ->setParameter('corporation_id', $corporationId);

        if ($this->active > 0 && ($activeDate = date_create(self::NOW.' -'.$this->active.' '.self::DAYS))) {
            $qb->andWhere('m.logonDate >= :active')->setParameter('active', $activeDate->format(self::DATE_FORMAT));
        }
        if ($this->inactive > 0 && ($inactiveDate = date_create(self::NOW.' -'.$this->inactive.' '.self::DAYS))) {
            $qb->andWhere('m.logonDate < :inactive')
                ->setParameter('inactive', $inactiveDate->format(self::DATE_FORMAT));
        }
        if ($this->account) {
            $qb->andWhere($qb->expr()->isNotNull('c.id'));
        } elseif ($this->account === false) {
            $qb->andWhere($qb->expr()->isNull('c.id'));
        }
        if ($this->tokenStatus !== null || $this->tokenChanged > 0) {
            $qb->andWhere('c.id IS NOT NULL');
        }
        if ($this->tokenStatus === self::TOKEN_STATUS_VALID) {
            $qb->andWhere($qb->expr()->eq('e.validToken', 1));
        } elseif ($this->tokenStatus === self::TOKEN_STATUS_INVALID) {
            $qb->andWhere($qb->expr()->eq('e.validToken', 0));
        } elseif ($this->tokenStatus === self::TOKEN_STATUS_NONE) {
            $qb->andWhere($qb->expr()->isNull('e.validToken'));
        }
        if (
            $this->tokenChanged > 0 &&
            ($tokenChangedDate = date_create(self::NOW.' -'.$this->tokenChanged.' '.self::DAYS))
        ) {
            $qb->andWhere('e.validTokenTime < :tokenChanged')
                ->setParameter('tokenChanged', $tokenChangedDate->format(self::DATE_FORMAT));
        }
        if ($this->mailCount) {
            $qb->andWhere($qb->expr()->gte('m.missingCharacterMailSentNumber', $this->mailCount));
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
            if ($r['missingCharacterMailSentDate']) {
                $member->setMissingCharacterMailSentDate($r['missingCharacterMailSentDate']);
            }
            $member->setMissingCharacterMailSentResult($r['missingCharacterMailSentResult']);
            $member->setMissingCharacterMailSentNumber($r['missingCharacterMailSentNumber']);

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
                $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
                $defaultToken = (new EsiToken())->setEveLogin($eveLogin);
                $defaultToken->setValidToken($r['validToken'] !== null ? (bool) $r['validToken'] : null);
                $character = (new Character())
                    ->setId((int) $r['characterId'])
                    ->setName($r['characterName'])
                    ->setMain((bool) $r['main'])
                    ->addEsiToken($defaultToken);
                if ($r['lastUpdate']) {
                    $character->setLastUpdate($r['lastUpdate']);
                }
                if ($r['created']) {
                    $character->setCreated($r['created']);
                }
                if ($r['validTokenTime']) {
                    $defaultToken->setValidTokenTime($r['validTokenTime']);
                }
                if ($r['lastChecked']) {
                    $defaultToken->setLastChecked($r['lastChecked']);
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
     * @return CorporationMember[]
     */
    public function findByCorporationsWithoutAccountAndActive(
        array $corporationIds,
        int $loginDays,
        ?int $dbResultLimit = null,
        int $offset = 0
    ): array {
        if (empty($corporationIds)) {
            return [];
        }
        if ($loginDays < 1) {
            return [];
        }

        $minLoginDate = date_create(self::NOW.' -'.$loginDays.' '.self::DAYS);
        if (! $minLoginDate) {
            return [];
        }

        $qb = $this->createQueryBuilder('m');
        $qb->leftJoin('Neucore\Entity\Character', 'c', 'WITH', 'm.id = c.id')
            ->where($qb->expr()->in('m.corporation', ':corporationIds'))
            ->andWhere($qb->expr()->isNull('c.id'))
            ->setParameter('corporationIds', $corporationIds)
            ->andWhere('m.logonDate > :minLoginDate')
            ->setParameter('minLoginDate', $minLoginDate->format(self::DATE_FORMAT))
            ->orderBy('m.name')
            ->setMaxResults($dbResultLimit) // don't use with JOIN
            ->setFirstResult($offset);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return int[]
     */
    public function fetchCorporationIds(): array
    {
        $corporations = $this->createQueryBuilder('m')
            ->leftJoin('m.corporation', 'c')
            ->select('c.id')
            ->groupBy('m.corporation')
            ->getQuery()
            ->getResult();

        return array_map(function ($corporation) {
            return (int) $corporation['id'];
        }, $corporations);
    }
}
