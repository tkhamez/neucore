<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
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
    public function playerLogins(): ResponseInterface
    {
        return $this->withJson($this->repositoryFactory->getPlayerLoginsRepository()->monthlySummary());
    }

    /**
     * @OA\Get(
     *     path="/user/statistics/total-monthly-app-requests",
     *     operationId="statisticsTotalMonthlyAppRequests",
     *     summary="Returns total monthly app request numbers, max. last 13 entries.",
     *     description="Needs role: statistics",
     *     tags={"Statistics"},
     *     security={{"Session"={}}},
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
    public function totalMonthlyAppRequests(): ResponseInterface
    {
        return $this->withJson($this->repositoryFactory->getAppRequestsRepository()->monthlySummary());
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
     *         name="start-time",
     *         in="query",
     *         description="Unix Timestamp",
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
        $startTime = $this->getQueryParam($request, 'start-time', time());
        return $this->withJson($this->repositoryFactory->getAppRequestsRepository()
            ->monthlySummaryByApp((int)$startTime));
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
     *         name="start-time",
     *         in="query",
     *         description="Unix Timestamp",
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
        $startTime = $this->getQueryParam($request, 'start-time', time());
        return $this->withJson($this->repositoryFactory->getAppRequestsRepository()->dailySummary((int)$startTime));
    }
}
