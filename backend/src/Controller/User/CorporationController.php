<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Corporation;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CorporationMemberRepository;
use Neucore\Service\Account;
use Neucore\Service\EsiData;
use Neucore\Service\ObjectManager;
use Neucore\Service\UserAuth;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[OA\Tag(
    name: 'Corporation',
    description: 'Corporation management (for automatic group assignment) and tracking.',
)]
#[OA\Schema(
    schema: 'TrackingDirector',
    required: ['id', 'name', 'playerId'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'playerId', type: 'integer'),
    ],
)]
class CorporationController extends BaseController
{
    private UserAuth $userAuth;

    private Account $accountService;

    private Corporation $corp;

    private Group $group;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        UserAuth $userAuth,
        Account $account,
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->userAuth = $userAuth;
        $this->accountService = $account;
    }

    #[OA\Get(
        path: '/user/corporation/find/{query}',
        operationId: 'userCorporationFind',
        description: 'Needs role: group-admin, watchlist-manager, settings',
        summary: 'Returns a list of corporations that matches the query (partial matching name or ticker).',
        security: [['Session' => []]],
        tags: ['Corporation'],
        parameters: [
            new OA\Parameter(
                name: 'query',
                description: 'Name or ticker of the corporation (min. 3 characters).',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', minLength: 3),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of corporations.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Corporation'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized'),
        ],
    )]
    public function find(string $query): ResponseInterface
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return $this->withJson([]);
        }

        $result = $this->repositoryFactory->getCorporationRepository()->findByNameOrTickerPartialMatch($query);

        return $this->withJson($this->minimalCorporationsResult($result));
    }

    #[OA\Post(
        path: '/user/corporation/corporations',
        operationId: 'userCorporationCorporations',
        description: 'Needs role: group-admin, watchlist-manager, settings',
        summary: 'Returns corporations found by ID.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            description: 'EVE IDs of corporations.',
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer')),
            ),
        ),
        tags: ['Corporation'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of corporations.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Corporation'),
                ),
            ),
            new OA\Response(response: '400', description: 'Invalid body.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
        ],
    )]
    public function corporations(ServerRequestInterface $request): ResponseInterface
    {
        $ids = $this->getIntegerArrayFromBody($request);

        if ($ids === null) {
            return $this->response->withStatus(400);
        }
        if (count($ids) === 0) {
            return $this->withJson([]);
        }

        $result = $this->repositoryFactory->getCorporationRepository()->findBy(['id' => $ids], ['name' => 'ASC']);

        return $this->withJson($this->minimalCorporationsResult($result));
    }

    #[OA\Get(
        path: '/user/corporation/with-groups',
        operationId: 'userCorporationWithGroups',
        description: 'Needs role: group-admin',
        summary: 'List all corporations that have groups assigned.',
        security: [['Session' => []]],
        tags: ['Corporation'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of corporations (this one includes the groups property).',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Corporation'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
        ],
    )]
    public function withGroups(): ResponseInterface
    {
        $result = [];
        foreach ($this->repositoryFactory->getCorporationRepository()->getAllWithGroups() as $corp) {
            // corporation model with groups
            $json = $corp->jsonSerialize();
            $json['groups'] = $corp->getGroups();
            $result[] = $json;
        }

        return $this->withJson($result);
    }

    #[OA\Post(
        path: '/user/corporation/add/{id}',
        operationId: 'userCorporationAdd',
        description: 'Needs role: group-admin, watchlist-manager<br>' .
            'This makes an ESI request and adds the corporation only if it exists. Also adds the ' .
            'corresponding alliance, if there is one.',
        summary: 'Add an EVE corporation to the database.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Corporation'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'EVE corporation ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '201',
                description: 'The new corporation.',
                content: new OA\JsonContent(ref: '#/components/schemas/Corporation'),
            ),
            new OA\Response(response: '400', description: 'Invalid corporation ID.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Corporation not found.'),
            new OA\Response(response: '409', description: 'The corporation already exists.'),
            new OA\Response(response: '503', description: 'ESI request failed.'),
        ],
    )]
    public function add(string $id, EsiData $esiData): ResponseInterface
    {
        $corpId = (int) $id;

        if ($this->repositoryFactory->getCorporationRepository()->find($corpId)) {
            return $this->response->withStatus(409);
        }

        // get corporation
        $corporation = $esiData->fetchCorporation($corpId, false);
        if ($corporation === null) {
            $code = $esiData->getLastErrorCode();
            if ($code === 404 || $code === 400) {
                return $this->response->withStatus($code);
            } else {
                return $this->response->withStatus(503);
            }
        }

        // fetch alliance
        if ($corporation->getAlliance() !== null) {
            $esiData->fetchAlliance($corporation->getAlliance()->getId(), false);
        }

        return $this->flushAndReturn(201, $corporation);
    }

    #[OA\Put(
        path: '/user/corporation/{id}/add-group/{gid}',
        operationId: 'userCorporationAddGroup',
        description: 'Needs role: group-admin',
        summary: 'Add a group to the corporation.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Corporation'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the corporation.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'gid',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Corporation and/or group not found.'),
        ],
    )]
    public function addGroup(string $id, string $gid): ResponseInterface
    {
        if (!$this->findCorpAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        if (!$this->corp->hasGroup($this->group->getId())) {
            $this->corp->addGroup($this->group);
        }

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/corporation/{id}/remove-group/{gid}',
        operationId: 'userCorporationRemoveGroup',
        description: 'Needs role: group-admin',
        summary: 'Remove a group from the corporation.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Corporation'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the corporation.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'gid',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Corporation and/or group not found.'),
        ],
    )]
    public function removeGroup(string $id, string $gid): ResponseInterface
    {
        if (!$this->findCorpAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        $this->corp->removeGroup($this->group);

        return $this->flushAndReturn(204);
    }

    #[OA\Get(
        path: '/user/corporation/{id}/tracking-director',
        operationId: 'corporationTrackingDirector',
        description: 'Needs role: tracking-admin',
        summary: 'Returns a list of directors with an ESI token for this corporation.',
        security: [['Session' => []]],
        tags: ['Corporation'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the corporation.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of directors.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/TrackingDirector'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
        ],
    )]
    public function trackingDirector(string $id): ResponseInterface
    {
        $repository = $this->repositoryFactory->getEsiTokenRepository();

        $directors = [];
        foreach ($repository->findByLoginAndCorporation(EveLogin::NAME_TRACKING, (int) $id) as $esiToken) {
            if ($esiToken->getValidToken() && $esiToken->getHasRoles() !== false && $esiToken->getCharacter()) {
                $directors[] = [
                    'id' => $esiToken->getCharacter()->getId(),
                    'name' => $esiToken->getCharacter()->getName(),
                    'playerId' => $esiToken->getCharacter()->getPlayer()->getId(),
                ];
            }
        }

        return $this->withJson($directors);
    }

    #[OA\Get(
        path: '/user/corporation/{id}/get-groups-tracking',
        operationId: 'getGroupsTracking',
        description: 'Needs role: tracking-admin',
        summary: 'Returns required groups to view member tracking data.',
        security: [['Session' => []]],
        tags: ['Corporation'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the corporation.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of groups.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Group'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Corporation not found.'),
        ],
    )]
    public function getGroupsTracking(string $id): ResponseInterface
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find((int) $id);

        if ($corporation === null) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($corporation->getGroupsTracking());
    }

    #[OA\Put(
        path: '/user/corporation/{id}/add-group-tracking/{groupId}',
        operationId: 'addGroupTracking',
        description: 'Needs role: tracking-admin',
        summary: 'Add a group to the corporation for member tracking permission.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Corporation'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the corporation.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'groupId',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Corporation and/or group not found.'),
        ],
    )]
    public function addGroupTracking(string $id, string $groupId): ResponseInterface
    {
        if (!$this->findCorpAndGroup($id, $groupId)) {
            return $this->response->withStatus(404);
        }

        if (!$this->corp->hasGroupTracking($this->group->getId())) {
            $this->corp->addGroupTracking($this->group);
            $this->accountService->syncTrackingRole(null, $this->corp);
        }

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/corporation/{id}/remove-group-tracking/{groupId}',
        operationId: 'removeGroupTracking',
        description: 'Needs role: tracking-admin',
        summary: 'Remove a group for member tracking permission from the corporation.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Corporation'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the corporation.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'groupId',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Corporation and/or group not found.'),
        ],
    )]
    public function removeGroupTracking(string $id, string $groupId): ResponseInterface
    {
        if (!$this->findCorpAndGroup($id, $groupId)) {
            return $this->response->withStatus(404);
        }

        $this->corp->removeGroupTracking($this->group);
        $this->accountService->syncTrackingRole(null, $this->corp);

        return $this->flushAndReturn(204);
    }

    #[OA\Get(
        path: '/user/corporation/tracked-corporations',
        operationId: 'corporationTrackedCorporations',
        description: 'Needs role: tracking and membership in appropriate group',
        summary: 'Returns corporations that have member tracking data.',
        security: [['Session' => []]],
        tags: ['Corporation'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of corporations.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Corporation'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
        ],
    )]
    public function trackedCorporations(): ResponseInterface
    {
        $corporations = $this->repositoryFactory->getCorporationRepository()->getAllWithMemberTrackingData();

        $result = [];
        foreach ($corporations as $corporation) {
            if ($this->checkPermission($corporation)) {
                $result[] = $corporation->jsonSerialize(true);
            }
        }

        return $this->withJson($result);
    }

    #[OA\Get(
        path: '/user/corporation/all-tracked-corporations',
        operationId: 'corporationAllTrackedCorporations',
        description: 'Needs role: tracking-admin',
        summary: 'Returns all corporations that have member tracking data.',
        security: [['Session' => []]],
        tags: ['Corporation'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of corporations.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Corporation'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
        ],
    )]
    public function allTrackedCorporations(): ResponseInterface
    {
        $corporations = $this->repositoryFactory->getCorporationRepository()->getAllWithMemberTrackingData();

        return $this->withJson(array_map(function (Corporation $corporation) {
            return $corporation->jsonSerialize(true);
        }, $corporations));
    }

    #[OA\Get(
        path: '/user/corporation/{id}/members',
        operationId: 'members',
        description: 'Needs role: tracking and membership in appropriate group',
        summary: 'Returns tracking data of corporation members.',
        security: [['Session' => []]],
        tags: ['Corporation'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the corporation.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'inactive',
                description: 'Limit to members who have been inactive for x days or longer.',
                in: 'query',
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'active',
                description: 'Limit to members who were active in the last x days.',
                in: 'query',
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'account',
                description: 'Limit to members with (true) or without (false) an account.',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['true', 'false']),
            ),
            new OA\Parameter(
                name: 'token-status',
                description: 'Limit to characters with a valid, invalid or no token.',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['valid', 'invalid', 'none']),
            ),
            new OA\Parameter(
                name: 'token-status-changed',
                description: 'Limit to characters whose ESI token status has not changed for x days.',
                in: 'query',
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'mail-count',
                description: "Limit to characters whose 'missing player' mail count is greater than or equal to x.",
                in: 'query',
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of corporation members.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/CorporationMember'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
        ],
    )]
    public function members(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find((int) $id);
        if (!$corporation || !$this->checkPermission($corporation)) {
            return $this->response->withStatus(403);
        }

        $inactive = $this->getQueryParam($request, 'inactive');
        $active = $this->getQueryParam($request, 'active');
        $accountParam = $this->getQueryParam($request, 'account');
        $tokenStatusParam = $this->getQueryParam($request, 'token-status');
        $tokenStatusChanged = $this->getQueryParam($request, 'token-status-changed');
        $mailCount = $this->getQueryParam($request, 'mail-count');

        if ($accountParam === 'true') {
            $account = true;
        } else {
            $account = $accountParam === 'false' ? false : null;
        }
        $tokenStatus = null;
        if ($tokenStatusParam === 'valid') {
            $tokenStatus = CorporationMemberRepository::TOKEN_STATUS_VALID;
        } elseif ($tokenStatusParam === 'invalid') {
            $tokenStatus = CorporationMemberRepository::TOKEN_STATUS_INVALID;
        } elseif ($tokenStatusParam === 'none') {
            $tokenStatus = CorporationMemberRepository::TOKEN_STATUS_NONE;
        }

        $members = $this->repositoryFactory
            ->getCorporationMemberRepository()
            ->setInactive($inactive !== null ? (int) $inactive : null)
            ->setActive($active !== null ? (int) $active : null)
            ->setAccount($account)
            ->setTokenStatus($tokenStatus)
            ->setTokenChanged($tokenStatusChanged !== null ? (int) $tokenStatusChanged : null)
            ->setMailCount((int) $mailCount)
            ->findMatching((int) $id);

        return $this->withJson($members);
    }

    private function findCorpAndGroup(string $corpId, string $groupId): bool
    {
        $corpEntity = $this->repositoryFactory->getCorporationRepository()->find((int) $corpId);
        $groupEntity = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);

        if ($corpEntity === null || $groupEntity === null) {
            return false;
        }

        $this->corp = $corpEntity;
        $this->group = $groupEntity;

        return true;
    }

    /**
     * Checks if logged-in user is member of a group that may see the member tracking data of a corporation.
     */
    private function checkPermission(Corporation $corporation): bool
    {
        $playerGroups = $this->getUser($this->userAuth)->getPlayer()->getGroupIds();
        foreach ($corporation->getGroupsTracking() as $group) {
            if (in_array($group->getId(), $playerGroups)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Corporation[] $corporations
     * @return array[]
     */
    private function minimalCorporationsResult(array $corporations): array
    {
        return array_map(function (Corporation $corporation) {
            return $corporation->jsonSerialize(false, false, false);
        }, $corporations);
    }
}
