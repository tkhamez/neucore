<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[OA\Tag(name: 'Statistics', description: 'Usage statistics.')]
class StatisticsController extends BaseController
{
    #[OA\Get(
        path: '/user/statistics/player-logins',
        operationId: 'statisticsPlayerLogins',
        description: 'Needs role: statistics',
        summary: 'Returns player login numbers, max. last 13 months.',
        security: [['Session' => []]],
        tags: ['Statistics'],
        parameters: [
            new OA\Parameter(
                name: 'until',
                description: 'Date: YYYY-MM',
                in: 'query',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'periods',
                description: 'Number of periods (months)',
                in: 'query',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Player logins.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/PlayerLoginStatistics')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
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

    #[OA\Get(
        path: '/user/statistics/total-monthly-app-requests',
        operationId: 'statisticsTotalMonthlyAppRequests',
        description: 'Needs role: statistics',
        summary: 'Returns total monthly app request numbers, max. last 13 entries.',
        security: [['Session' => []]],
        tags: ['Statistics'],
        parameters: [
            new OA\Parameter(
                name: 'until',
                description: 'Date: YYYY-MM',
                in: 'query',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'periods',
                description: 'Number of periods (months)',
                in: 'query',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'App requests.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/TotalMonthlyAppRequests')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
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

    #[OA\Get(
        path: '/user/statistics/monthly-app-requests',
        operationId: 'statisticsMonthlyAppRequests',
        description: 'Needs role: statistics',
        summary: 'Returns monthly app request numbers.',
        security: [['Session' => []]],
        tags: ['Statistics'],
        parameters: [
            new OA\Parameter(
                name: 'until',
                description: 'Date: YYYY-MM',
                in: 'query',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'periods',
                description: 'Number of periods (months)',
                in: 'query',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'App requests.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/MonthlyAppRequests')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
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

    #[OA\Get(
        path: '/user/statistics/total-daily-app-requests',
        operationId: 'statisticsTotalDailyAppRequests',
        description: 'Needs role: statistics',
        summary: 'Returns total daily app request numbers.',
        security: [['Session' => []]],
        tags: ['Statistics'],
        parameters: [
            new OA\Parameter(
                name: 'until',
                description: 'Date: YYYY-MM-DD',
                in: 'query',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'periods',
                description: 'Number of periods (weeks)',
                in: 'query',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'App requests.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/TotalDailyAppRequests')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
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

    #[OA\Get(
        path: '/user/statistics/hourly-app-requests',
        operationId: 'statisticsHourlyAppRequests',
        description: 'Needs role: statistics',
        summary: 'Returns hourly app request numbers.',
        security: [['Session' => []]],
        tags: ['Statistics'],
        parameters: [
            new OA\Parameter(
                name: 'until',
                description: 'Date: YYYY-MM-DD HH',
                in: 'query',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'periods',
                description: 'Number of periods (days)',
                in: 'query',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'App requests.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/HourlyAppRequests')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
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
