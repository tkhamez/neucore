<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\AppRequests;
use Neucore\Repository\Traits\DateHelper;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;

/**
 * @method AppRequests|null find($id, $lockMode = null, $lockVersion = null)
 * @method AppRequests|null findOneBy(array $criteria, array $orderBy = null)
 * @method AppRequests[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @psalm-suppress MissingTemplateParam
 */
class AppRequestsRepository extends EntityRepository
{
    use DateHelper;

    /**
     * @OA\Schema(
     *     schema="TotalMonthlyAppRequests",
     *     required={"requests", "year", "month"},
     *     @OA\Property(property="requests", type="integer"),
     *     @OA\Property(property="year", type="integer"),
     *     @OA\Property(property="month", type="integer"),
     * )
     */
    public function monthlySummary(int $endDate, int $months): array
    {
        $startDate = strtotime(date('Y-m-d H:i:s', $endDate) . " -$months months") ?: time();
        $start = $this->getDateNumber($startDate);
        $end = $this->getDateNumber($endDate);

        $qb = $this->createQueryBuilder('ar');
        $qb->select([
                'SUM(ar.count) AS requests',
                'ar.year',
                'ar.month',
            ])
            ->where($qb->expr()->gt('(ar.year * 100) + ar.month', ':start'))
            ->setParameter('start', $start)
            ->andWhere($qb->expr()->lte('(ar.year * 100) + ar.month', ':end'))
            ->setParameter('end', $end)
            ->groupBy('ar.year', 'ar.month')
            ->orderBy('ar.year', 'DESC')->addOrderBy('ar.month', 'DESC')
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
     */
    public function monthlySummaryByApp(int $endDate, int $months): array
    {
        $startDate = strtotime(date('Y-m-d H:i:s', $endDate) . " -$months months") ?: time();
        $start = $this->getDateNumber($startDate);
        $end = $this->getDateNumber($endDate);

        $qb = $this->createQueryBuilder('ar');
        $qb->join('ar.app', 'a')
            ->select([
                'a.id AS app_id',
                'a.name AS app_name',
                'SUM(ar.count) AS requests',
                'ar.year',
                'ar.month',
            ])
            ->where($qb->expr()->gt('(ar.year * 100) + ar.month', ':start'))
            ->setParameter('start', $start)
            ->andWhere($qb->expr()->lte('(ar.year * 100) + ar.month', ':end'))
            ->setParameter('end', $end)
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
     */
    public function dailySummary(int $endDate, int $weeks): array
    {
        $startDate = strtotime(date('Y-m-d H:i:s', $endDate) . " -$weeks weeks") ?: time();
        $start = $this->getDateNumber($startDate, 2);
        $end = $this->getDateNumber($endDate, 2);

        $qb = $this->createQueryBuilder('ar');
        $qb->select([
            'SUM(ar.count) AS requests',
            'ar.year',
            'ar.month',
            'ar.dayOfMonth AS day_of_month',
        ])
            ->where($qb->expr()->gt('((ar.year * 100) + ar.month) * 100 + ar.dayOfMonth', ':start'))
            ->setParameter('start', $start)
            ->andWhere($qb->expr()->lte('((ar.year * 100) + ar.month) * 100 + ar.dayOfMonth', ':end'))
            ->setParameter('end', $end)
            ->groupBy('ar.year', 'ar.month', 'ar.dayOfMonth')
            ->orderBy('ar.year', 'DESC')->addOrderBy('ar.month', 'DESC')->addOrderBy('ar.dayOfMonth', 'DESC');

        return array_map(function (array $r) {
            $r['requests'] = (int)$r['requests']; // it's a string for some reason
            return $r;
        }, $qb->getQuery()->getResult());
    }

    /**
     * @OA\Schema(
     *     schema="HourlyAppRequests",
     *     required={"app_id", "app_name", "requests", "year", "month", "day_of_month", "hour"},
     *     @OA\Property(property="app_id", type="integer"),
     *     @OA\Property(property="app_name", type="string"),
     *     @OA\Property(property="requests", type="integer"),
     *     @OA\Property(property="year", type="integer"),
     *     @OA\Property(property="month", type="integer"),
     *     @OA\Property(property="day_of_month", type="integer"),
     *     @OA\Property(property="hour", type="integer"),
     * )
     */
    public function hourlySummary(int $endDateTime, int $days): array
    {
        $startTime = strtotime(date('Y-m-d H:i:s', $endDateTime) . " -$days days") ?: time();
        $start = $this->getDateNumber($startTime, 3);
        $end = $this->getDateNumber($endDateTime, 3);

        $qb = $this->createQueryBuilder('ar');
        $qb->join('ar.app', 'a')
            ->select([
            'a.id AS app_id',
            'a.name AS app_name',
            'SUM(ar.count) AS requests',
            'ar.year',
            'ar.month',
            'ar.dayOfMonth AS day_of_month',
            'ar.hour AS hour',
        ])
            ->where($qb->expr()->gt(
                '((((ar.year * 100) + ar.month) * 100 + ar.dayOfMonth) * 100 + ar.hour)',
                ':start'
            ))
            ->setParameter('start', $start)
            ->andWhere($qb->expr()->lte(
                '((((ar.year * 100) + ar.month) * 100 + ar.dayOfMonth) * 100 + ar.hour)',
                ':end'
            ))
            ->setParameter('end', $end)
            ->groupBy('app_id', 'ar.year', 'ar.month', 'ar.dayOfMonth', 'ar.hour')
            ->orderBy('ar.year', 'DESC')
            ->addOrderBy('ar.month', 'DESC')
            ->addOrderBy('ar.dayOfMonth', 'DESC')
            ->addOrderBy('ar.hour', 'DESC')
            ->addOrderBy('a.id');

        return array_map(function (array $r) {
            $r['requests'] = (int)$r['requests']; // it's a string for some reason
            return $r;
        }, $qb->getQuery()->getResult());
    }
}
