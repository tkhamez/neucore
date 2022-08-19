<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\PlayerLogins;
use Neucore\Repository\Traits\DateHelper;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * @method PlayerLogins|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlayerLogins|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlayerLogins[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlayerLoginsRepository extends EntityRepository
{
    use DateHelper;

    /**
     * @OA\Schema(
     *     schema="PlayerLoginStatistics",
     *     required={"unique_logins", "total_logins", "year", "month"},
     *     @OA\Property(property="unique_logins", type="integer"),
     *     @OA\Property(property="total_logins", type="integer"),
     *     @OA\Property(property="year", type="integer"),
     *     @OA\Property(property="month", type="integer"),
     * )
     */
    public function monthlySummary(int $endDate, int $months): array
    {
        $startDate = strtotime(date('Y-m-d H:i:s', $endDate) . " -$months months") ?: time();
        $start = $this->getDateNumber($startDate);
        $end = $this->getDateNumber($endDate);

        $qb = $this->createQueryBuilder('pl');
        $qb->select([
                'COUNT(pl.player) AS unique_logins',
                'SUM(pl.count) AS total_logins',
                'pl.year',
                'pl.month',
            ])
            ->where($qb->expr()->gt('(pl.year * 100) + pl.month', ':start'))
            ->setParameter('start', $start)
            ->andWhere($qb->expr()->lte('(pl.year * 100) + pl.month', ':end'))
            ->setParameter('end', $end)
            ->groupBy('pl.year', 'pl.month')
            ->orderBy('pl.year', 'DESC')->addOrderBy('pl.month', 'DESC')
        ;

        return array_map(function (array $r) {
            $r['total_logins'] = (int)$r['total_logins']; // it's a string for some reason
            return $r;
        }, $qb->getQuery()->getResult());
    }
}
