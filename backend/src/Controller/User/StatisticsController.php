<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="Statistics",
 *     description="Usage statistics."
 * )
 */
class StatisticsController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/user/statistics/player-logins",
     *     operationId="statisticsPlayerLogins",
     *     summary="Returns player login numbers, max. last 13 months.",
     *     description="Needs role: statistics",
     *     tags={"Statistics"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="until",
     *         in="query",
     *         description="Date: YYYY-MM",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="periods",
     *         in="query",
     *         description="Number of periods (months)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Player logins.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/PlayerLoginStatistics"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function playerLogins(ServerRequestInterface $request): ResponseInterface
    {
        $dateString = $this->getQueryParam($request, 'until', date('Y-m-01'));
        try {
            $date = new \DateTime($dateString);
        } catch (\Exception) {
            // do nothing
        }
        $time = isset($date) ? $date->getTimestamp() : time();
        $periods = abs((int)$this->getQueryParam($request, 'periods', 12));
        return $this->withJson($this->repositoryFactory->getPlayerLoginsRepository()->monthlySummary($time, $periods));
    }

    /**
     * @OA\Get(
     *     path="/user/statistics/total-monthly-app-requests",
     *     operationId="statisticsTotalMonthlyAppRequests",
     *     summary="Returns total monthly app request numbers, max. last 13 entries.",
     *     description="Needs role: statistics",
     *     tags={"Statistics"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="until",
     *         in="query",
     *         description="Date: YYYY-MM",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="periods",
     *         in="query",
     *         description="Number of periods (months)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="App requests.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/TotalMonthlyAppRequests"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function totalMonthlyAppRequests(ServerRequestInterface $request): ResponseInterface
    {
        $dateString = $this->getQueryParam($request, 'until', date('Y-m-01'));
        try {
            $date = new \DateTime($dateString);
        } catch (\Exception) {
            // do nothing
        }
        $time = isset($date) ? $date->getTimestamp() : time();
        $periods = abs((int)$this->getQueryParam($request, 'periods', 12));
        return $this->withJson($this->repositoryFactory->getAppRequestsRepository()->monthlySummary($time, $periods));
    }

    /**
     * @OA\Get(
     *     path="/user/statistics/monthly-app-requests",
     *     operationId="statisticsMonthlyAppRequests",
     *     summary="Returns monthly app request numbers.",
     *     description="Needs role: statistics",
     *     tags={"Statistics"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="until",
     *         in="query",
     *         description="Date: YYYY-MM",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="periods",
     *         in="query",
     *         description="Number of periods (months)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="App requests.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/MonthlyAppRequests"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function monthlyAppRequests(ServerRequestInterface $request): ResponseInterface
    {
        $dateString = $this->getQueryParam($request, 'until', date('Y-m-01'));
        try {
            $date = new \DateTime($dateString);
        } catch (\Exception) {
            // do nothing
        }
        $time = isset($date) ? $date->getTimestamp() : time();
        $periods = abs((int)$this->getQueryParam($request, 'periods', 12));
        return $this->withJson($this->repositoryFactory->getAppRequestsRepository()
            ->monthlySummaryByApp($time, $periods));
    }

    /**
     * @OA\Get(
     *     path="/user/statistics/total-daily-app-requests",
     *     operationId="statisticsTotalDailyAppRequests",
     *     summary="Returns total daily app request numbers.",
     *     description="Needs role: statistics",
     *     tags={"Statistics"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="until",
     *         in="query",
     *         description="Date: YYYY-MM-DD",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="periods",
     *         in="query",
     *         description="Number of periods (weeks)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="App requests.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/TotalDailyAppRequests"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function totalDailyAppRequests(ServerRequestInterface $request): ResponseInterface
    {
        $dateString = $this->getQueryParam($request, 'until', date('Y-m-d'));
        try {
            $date = new \DateTime($dateString);
        } catch (\Exception) {
            // do nothing
        }
        $time = isset($date) ? $date->getTimestamp() : time();
        $periods = abs((int)$this->getQueryParam($request, 'periods', 4));
        return $this->withJson($this->repositoryFactory->getAppRequestsRepository()->dailySummary($time, $periods));
    }

    /**
     * @OA\Get(
     *     path="/user/statistics/hourly-app-requests",
     *     operationId="statisticsHourlyAppRequests",
     *     summary="Returns hourly app request numbers.",
     *     description="Needs role: statistics",
     *     tags={"Statistics"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="until",
     *         in="query",
     *         description="Date: YYYY-MM-DD HH",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="periods",
     *         in="query",
     *         description="Number of periods (days)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="App requests.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/HourlyAppRequests"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function hourlyAppRequests(ServerRequestInterface $request): ResponseInterface
    {
        $dateString = $this->getQueryParam($request, 'until', date('Y-m-d H')) . ':00';
        try {
            $date = new \DateTime($dateString);
        } catch (\Exception) {
            // do nothing
        }
        $time = isset($date) ? $date->getTimestamp() : time();
        $periods = abs((int)$this->getQueryParam($request, 'periods', 7));
        return $this->withJson($this->repositoryFactory->getAppRequestsRepository()->hourlySummary($time, $periods));
    }
}
