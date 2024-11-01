<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Alliance;
use Neucore\Entity\Group;
use Neucore\Service\EsiData;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[OA\Tag(name: 'Alliance', description: 'Alliance management (for automatic group assignment).')]
class AllianceController extends BaseController
{
    private Alliance $alliance;

    private Group $group;

    #[OA\Get(
        path: '/user/alliance/find/{query}',
        operationId: 'userAllianceFind',
        description: 'Needs role: group-admin, watchlist-manager, settings',
        summary: 'Returns a list of alliances that matches the query (partial matching name or ticker).',
        security: [['Session' => []]],
        tags: ['Alliance'],
        parameters: [
            new OA\Parameter(
                name: 'query',
                description: 'Name or ticker of the alliance (min. 3 characters).',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', minLength: 3)
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of alliances.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Alliance')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized')
        ],
    )]
    public function find(string $query): ResponseInterface
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return $this->withJson([]);
        }

        $result = $this->repositoryFactory->getAllianceRepository()->findByNameOrTickerPartialMatch($query);

        return $this->withJson($result);
    }

    #[OA\Post(
        path: '/user/alliance/alliances',
        operationId: 'userAllianceAlliances',
        description: 'Needs role: group-admin, watchlist-manager, settings',
        summary: 'Returns alliances found by ID.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            description: 'EVE IDs of alliances.',
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))
            )
        ),
        tags: ['Alliance'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of alliances.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Alliance')
                )
            ),
            new OA\Response(response: '400', description: 'Invalid body.'),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function alliances(ServerRequestInterface $request): ResponseInterface
    {
        $ids = $this->getIntegerArrayFromBody($request);

        if ($ids === null) {
            return $this->response->withStatus(400);
        }
        if (empty($ids)) {
            return $this->withJson([]);
        }

        $result = $this->repositoryFactory->getAllianceRepository()->findBy(['id' => $ids], ['name' => 'ASC']);

        return $this->withJson($result);
    }

    #[OA\Get(
        path: '/user/alliance/with-groups',
        operationId: 'withGroups',
        description: 'Needs role: group-admin',
        summary: 'List all alliances that have groups assigned.',
        security: [['Session' => []]],
        tags: ['Alliance'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of alliances (this one includes the groups property).',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Alliance')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function withGroups(): ResponseInterface
    {
        $result = [];
        foreach ($this->repositoryFactory->getAllianceRepository()->getAllWithGroups() as $alliance) {
            // alliance model with groups
            $json = $alliance->jsonSerialize();
            $json['groups'] = $alliance->getGroups();
            $result[] = $json;
        }

        return $this->withJson($result);
    }

    #[OA\Post(
        path: '/user/alliance/add/{id}',
        operationId: 'add',
        description: 'Needs role: group-admin, watchlist-manager.<br>' .
            'This makes an ESI request and adds the alliance only if it exists',
        summary: 'Add an EVE alliance to the database.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Alliance'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'EVE alliance ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '201',
                description: 'The new alliance.',
                content: new OA\JsonContent(ref: '#/components/schemas/Alliance')
            ),
            new OA\Response(response: '400', description: 'Invalid alliance ID.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Alliance not found.'),
            new OA\Response(response: '409', description: 'The alliance already exists.'),
            new OA\Response(response: '503', description: 'ESI request failed.')
        ],
    )]
    public function add(string $id, EsiData $service): ResponseInterface
    {
        $allianceId = (int)$id;

        if ($this->repositoryFactory->getAllianceRepository()->find($allianceId)) {
            return $this->response->withStatus(409);
        }

        // get alliance
        $newAlliance = $service->fetchAlliance($allianceId, false);
        if ($newAlliance === null) {
            $code = $service->getLastErrorCode();
            if ($code === 404 || $code === 400) {
                return $this->response->withStatus($code);
            } else {
                return $this->response->withStatus(503);
            }
        }

        return $this->flushAndReturn(201, $newAlliance);
    }

    #[OA\Put(
        path: '/user/alliance/{id}/add-group/{gid}',
        operationId: 'addGroup',
        description: 'Needs role: group-admin',
        summary: 'Add a group to the alliance.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Alliance'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the alliance.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'gid',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Alliance and/or group not found.')
        ],
    )]
    public function addGroup(string $id, string $gid): ResponseInterface
    {
        if (!$this->findAllianceAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        if (!$this->alliance->hasGroup($this->group->getId())) {
            $this->alliance->addGroup($this->group);
        }

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/alliance/{id}/remove-group/{gid}',
        operationId: 'removeGroup',
        description: 'Needs role: group-admin',
        summary: 'Remove a group from the alliance.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Alliance'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the alliance.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'gid',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Alliance and/or group not found.')
        ],
    )]
    public function removeGroup(string $id, string $gid): ResponseInterface
    {
        if (!$this->findAllianceAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        $this->alliance->removeGroup($this->group);

        return $this->flushAndReturn(204);
    }

    private function findAllianceAndGroup(string $allianceId, string $groupId): bool
    {
        $allianceEntity = $this->repositoryFactory->getAllianceRepository()->find((int)$allianceId);
        $groupEntity = $this->repositoryFactory->getGroupRepository()->find((int)$groupId);

        if ($allianceEntity === null || $groupEntity === null) {
            return false;
        }

        $this->alliance = $allianceEntity;
        $this->group = $groupEntity;

        return true;
    }
}
