<?php declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\CorporationMember;

/**
 * @method CorporationMember|null find($id, $lockMode = null, $lockVersion = null)
 * @method CorporationMember[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CorporationMemberRepository extends EntityRepository
{
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
    public function resetCriteria()
    {
        $this->setInactive(null);
        $this->setActive(null);
        $this->setAccount(null);
        $this->setValidToken(null);
        $this->setTokenChanged(null);

        return $this;
    }

    /**
     * @param int $corporationId EVE corporation ID
     * @return CorporationMember[]
     */
    public function findMatching(int $corporationId): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.corporation = :corporation_id')->setParameter('corporation_id', $corporationId)
            ->orderBy('c.logonDate', 'DESC');

        if ($this->active > 0) {
            if ($activeDate = date_create('now -'.$this->active.' days')) {
                $qb->andWhere('c.logonDate >= :active')
                    ->setParameter('active', $activeDate->format('Y-m-d H:i:s'));
            }
        }

        if ($this->inactive > 0) {
            if ($inactiveDate = date_create('now -'.$this->inactive.' days')) {
                $qb->andWhere('c.logonDate < :inactive')
                    ->setParameter('inactive', $inactiveDate->format('Y-m-d H:i:s'));
            }
        }

        if ($this->account) {
            $qb->andWhere($qb->expr()->isNotNull('c.character'));
        } elseif ($this->account === false) {
            $qb->andWhere($qb->expr()->isNull('c.character'));
        }

        if ($this->validToken !== null || $this->tokenChanged > 0) {
            $qb->leftJoin('c.character', 'char');
            $qb->andWhere('char.id IS NOT NULL');
        }

        if ($this->validToken) {
            $qb->andWhere($qb->expr()->eq('char.validToken', 1));
        } elseif ($this->validToken === false) {
            $qb->andWhere($qb->expr()->eq('char.validToken', 0));
        }

        if ($this->tokenChanged > 0) {
            if ($tokenChangedDate = date_create('now -'.$this->tokenChanged.' days')) {
                $qb->andWhere('char.validTokenTime < :tokenChanged')
                    ->setParameter('tokenChanged', $tokenChangedDate->format('Y-m-d H:i:s'));
            }
        }

        return $qb->getQuery()->getResult();
    }
}
