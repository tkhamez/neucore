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
     * Limit to members who were active in the last x days.
     *
     * @see CorporationMemberRepository::findMatching()
     */
    public function setActive(?int $days): self
    {
        $this->active = $days;

        return $this;
    }

    /**
     * Limit to members who have been inactive for x days or longer.
     *
     * @see CorporationMemberRepository::findMatching()
     */
    public function setInactive(?int $days): self
    {
        $this->inactive = $days;

        return $this;
    }

    /**
     * Limit to members with (true) or without (false) an account
     *
     * @see CorporationMemberRepository::findMatching()
     */
    public function setAccount(?bool $account): self
    {
        $this->account = $account;

        return $this;
    }

    /**
     * Reset filter variables.
     *
     * @see CorporationMemberRepository::setActive()
     * @see CorporationMemberRepository::setInactive()
     * @see CorporationMemberRepository::setAccount()
     */
    public function resetCriteria()
    {
        $this->setInactive(null);
        $this->setActive(null);
        $this->setAccount(null);

        return $this;
    }

    /**
     * @param int $corporationId EVE corporation ID
     * @return CorporationMember[]
     * @see CorporationMemberRepository::setActive()
     * @see CorporationMemberRepository::setInactive()
     * @see CorporationMemberRepository::setAccount()
     */
    public function findMatching(int $corporationId): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.corporation = :corporation_id')->setParameter('corporation_id', $corporationId)
            ->orderBy('c.logonDate', 'DESC');

        if ($this->active > 0) {
            $activeDate = date_create('now -'.$this->active.' days');
            $qb->andWhere('c.logonDate >= :active')->setParameter('active', $activeDate->format('Y-m-d'));
        }

        if ($this->inactive > 0) {
            $inactiveDate = date_create('now -'.$this->inactive.' days');
            $qb->andWhere('c.logonDate < :inactive')->setParameter('inactive', $inactiveDate->format('Y-m-d'));
        }

        if ($this->account) {
            $qb->andWhere($qb->expr()->isNotNull('c.character'));
        } elseif ($this->account === false) {
            $qb->andWhere($qb->expr()->isNull('c.character'));
        }

        return $qb->getQuery()->getResult();
    }
}
