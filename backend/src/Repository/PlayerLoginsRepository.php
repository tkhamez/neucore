<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\PlayerLogins;
use OpenApi\Annotations as OA;

/**
 * @method PlayerLogins|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlayerLogins|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlayerLogins[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlayerLoginsRepository extends EntityRepository
{
    /**
     * @OA\Schema(
     *     schema="PlayerLoginStatistics",
     *     required={"unique_logins", "total_logins", "year", "month"},
     *     @OA\Property(property="unique_logins", type="integer"),
     *     @OA\Property(property="total_logins", type="integer"),
     *     @OA\Property(property="year", type="integer"),
     *     @OA\Property(property="month", type="integer"),
     * )
     *
     * Returns max. 13 rows, newest first.
     */
    public function monthlySummary(): array
    {
        $qb = $this->createQueryBuilder('pl')
            ->select(
                'COUNT(pl.player) AS unique_logins',
                'SUM(pl.count) AS total_logins',
                'pl.year',
                'pl.month',
            )
            ->groupBy('pl.year', 'pl.month')
            ->orderBy('pl.year', 'DESC')->addOrderBy('pl.month', 'DESC')
            ->setMaxResults(13)
        ;

        return array_map(function (array $r) {
            $r['total_logins'] = (int)$r['total_logins']; // it's a string for some reason
            return $r;
        }, $qb->getQuery()->getResult());
    }
}
