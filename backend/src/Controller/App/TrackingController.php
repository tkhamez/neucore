<?php

declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Controller\BaseController;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[OA\Tag(name: 'Application - Tracking')]
class TrackingController extends BaseController
{
    #[OA\Get(
        path: '/app/v1/corporation/{id}/member-tracking',
        operationId: 'memberTrackingV1',
        description: 'Needs role: app-tracking',
        summary: 'Return corporation member tracking data.',
        security: [['BearerAuth' => []]],
        tags: ['Application - Tracking'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'EVE corporation ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'inactive',
                description: 'Limit to members who have been inactive for x days or longer.',
                in: 'query',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'active',
                description: 'Limit to members who were active in the last x days.',
                in: 'query',
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'account',
                description: 'Limit to members with (true) or without (false) an account.',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['true', 'false'])
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Members ordered by logonDate descending (character and player properties excluded).',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CorporationMember')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '500', description: '', content: new OA\JsonContent(type: 'string'))
        ],
    )]
    public function memberTrackingV1(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $inactive = $this->getQueryParam($request, 'inactive');
        $active = $this->getQueryParam($request, 'active');
        $accountParam = $this->getQueryParam($request, 'account');
        if ($accountParam === 'true') {
            $account = true;
        } else {
            $account = $accountParam === 'false' ? false : null;
        }

        $members = $this->repositoryFactory
            ->getCorporationMemberRepository()
            ->setInactive($inactive !== null ? (int)$inactive : null)
            ->setActive($active !== null ? (int)$active : null)
            ->setAccount($account)
            ->findMatching((int)$id);

        $result = [];
        foreach ($members as $member) {
            $result[] = $member->jsonSerialize(false);
        }

        return $this->withJson($result);
    }
}
