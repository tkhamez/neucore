<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\EsiToken;

/**
 * @method EsiToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method EsiToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method EsiToken[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EsiTokenRepository extends EntityRepository
{
    /**
     * @return EsiToken[]
     */
    public function findByLoginAndCorporation(string $loginName, int $corporationId): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.eveLogin', 'l')
            ->join('e.character', 'char')
            ->join('char.corporation', 'corp')
            ->andWhere('l.name = :name')
            ->andWhere('corp.id = :id')
            ->setParameter('name', $loginName)
            ->setParameter('id', $corporationId)
            ->orderBy('char.name')
            ->getQuery()
            ->getResult();
    }
}
