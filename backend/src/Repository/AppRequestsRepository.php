<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\AppRequests;
use OpenApi\Annotations as OA;

/**
 * @method AppRequests|null find($id, $lockMode = null, $lockVersion = null)
 * @method AppRequests|null findOneBy(array $criteria, array $orderBy = null)
 * @method AppRequests[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppRequestsRepository extends EntityRepository
{
    /**
     * @OA\Schema(
     *     schema="TotalMonthlyAppRequests",
     *     required={"requests", "year", "month"},
     *     @OA\Property(property="requests", type="integer"),
     *     @OA\Property(property="year", type="integer"),
     *     @OA\Property(property="month", type="integer"),
     * )
     *
     * Returns max. 13 rows, newest first.
     */
    public function monthlySummary(): array
    {
        $qb = $this->createQueryBuilder('ar')
            ->select(
                'SUM(ar.count) AS requests',
                'ar.year',
                'ar.month',
            )
            ->groupBy('ar.year', 'ar.month')
            ->orderBy('ar.year', 'DESC')->addOrderBy('ar.month', 'DESC')
            ->setMaxResults(13)
        ;

        return array_map(function (array $r) {
            $r['requests'] = (int)$r['requests']; // it's a string for some reason
            return $r;
        }, $qb->getQuery()->getResult());
    }

    /**
     * @OA\Schema(
     *     schema="MonthlyAppRequests",
     *     required={"app_id", "app_name", "requests", "year", "month"},
     *     @OA\Property(property="app_id", type="integer"),
     *     @OA\Property(property="app_name", type="string"),
     *     @OA\Property(property="requests", type="integer"),
     *     @OA\Property(property="year", type="integer"),
     *     @OA\Property(property="month", type="integer"),
     * )
     *
     * Returns max. 13 months, newest first.
     */
    public function monthlySummaryByApp(): array
    {
        $year = (int)date('Y');
        $month = (int)date('m');
        $monthBeforeMin = $month === 1 ? 12 : $month - 1;
        $yearOfMonthBeforeMin = $month === 1 ? $year - 2 : $year - 1;
        $yearMonthBeforeMin = ($yearOfMonthBeforeMin * 100) + $monthBeforeMin;

        $qb = $this->createQueryBuilder('ar');
        $qb->join('ar.app', 'a')
            ->select(
                'a.id AS app_id',
                'a.name AS app_name',
                'SUM(ar.count) AS requests',
                'ar.year',
                'ar.month',
                //'(ar.year * 100) + ar.month',
            )
            ->where($qb->expr()->gt('(ar.year * 100) + ar.month', ':yearMonth'))
            ->setParameter('yearMonth', $yearMonthBeforeMin)
            ->groupBy('app_id', 'ar.year', 'ar.month')
            ->orderBy('ar.year', 'DESC')->addOrderBy('ar.month', 'DESC')->addOrderBy('app_id')
        ;

        return array_map(function (array $r) {
            // they are strings for some reason
            $r['requests'] = (int)$r['requests'];
            $r['app_id'] = (int)$r['app_id'];

            return $r;
        }, $qb->getQuery()->getResult());
    }

    /**
     * @OA\Schema(
     *     schema="TotalDailyAppRequests",
     *     required={"requests", "year", "month", "day_of_month"},
     *     @OA\Property(property="requests", type="integer"),
     *     @OA\Property(property="year", type="integer"),
     *     @OA\Property(property="month", type="integer"),
     *     @OA\Property(property="day_of_month", type="integer"),
     * )
     *
     * Returns max. 2 months, newest first.
     */
    public function dailySummary(): array
    {
        $year = (int)date('Y');
        $month = (int)date('m');
        $monthMinus2 = $month <= 2 ? 10 + $month : $month - 2;
        $yearOfMonthMinus2 = $month <= 2 ? $year - 1 : $year;
        $yearMonthMinus2 = ($yearOfMonthMinus2 * 100) + $monthMinus2;

        $qb = $this->createQueryBuilder('ar');
        $qb->select(
            'SUM(ar.count) AS requests',
            'ar.year',
            'ar.month',
            'ar.dayOfMonth AS day_of_month',
            //'(ar.year * 100) + ar.month',
        )
            ->where($qb->expr()->gt('(ar.year * 100) + ar.month', ':yearMonth'))
            ->setParameter('yearMonth', $yearMonthMinus2)
            ->groupBy('ar.year', 'ar.month', 'ar.dayOfMonth')
            ->orderBy('ar.year', 'DESC')->addOrderBy('ar.month', 'DESC')->addOrderBy('ar.dayOfMonth', 'DESC');

        return array_map(function (array $r) {
            $r['requests'] = (int)$r['requests']; // it's a string for some reason
            return $r;
        }, $qb->getQuery()->getResult());
    }
}
