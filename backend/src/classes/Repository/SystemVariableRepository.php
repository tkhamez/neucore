<?php declare(strict_types=1);

namespace Neucore\Repository;

use Neucore\Entity\SystemVariable;

/**
 * System Variable Repository
 *
 * @method SystemVariable|null find($id, $lockMode = null, $lockVersion = null)
 * @method SystemVariable[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method SystemVariable|null findOneBy(array $criteria, array $orderBy = null)
 */
class SystemVariableRepository extends \Doctrine\ORM\EntityRepository
{
    /**
     * @return SystemVariable[]
     */
    public function getDirectors(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.name LIKE :name')
            ->setParameter('name', SystemVariable::DIRECTOR_CHAR . '%')
            ->orderBy('s.name')
            ->getQuery()
            ->getResult();
    }
}
