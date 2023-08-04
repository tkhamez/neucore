<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\Corporation;
use Neucore\Util\Database;

/**
 * CorporationRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 *
 * @method Corporation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Corporation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Corporation[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @psalm-suppress MissingTemplateParam
 */
class CorporationRepository extends EntityRepository
{
    /**
     * @return Corporation[]
     */
    public function getAllWithGroups(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.groups', 'g')
            ->andWhere('g.id IS NOT NULL')
            ->orderBy('c.name')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Corporation[]
     */
    public function getAllWithMemberTrackingData(): array
    {
        // TODO this is a bad query, joins too much, CPU intensive

        return $this->createQueryBuilder('c')
            ->join('c.members', 'm')
            ->andWhere('m.corporation IS NOT NULL')
            ->orderBy('c.name')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array $corporationIds
     * @return Corporation[]
     */
    public function getAllFromAlliances(array $corporationIds): array
    {
        $qb = $this->createQueryBuilder('c');
        return $qb
            ->leftJoin('c.alliance', 'a')
            ->where($qb->expr()->in('a.id', ':ids'))
            ->orderBy('c.id')
            ->setParameter('ids', $corporationIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Corporation[]
     */
    public function findByNameOrTickerPartialMatch(string $search): array
    {
        $search = Database::escapeForLike($this->getEntityManager(), $search);

        $query = $this->createQueryBuilder('c')
            ->where('c.name LIKE :search')
            ->orWhere('c.ticker LIKE :search')
            ->addOrderBy('c.name', 'ASC')
            ->setParameter('search', "%$search%");

        return $query->getQuery()->getResult();
    }
}
