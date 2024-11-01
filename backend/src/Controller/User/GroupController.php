<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Account;
use Neucore\Service\AccountGroup;
use Neucore\Service\ObjectManager;
use Neucore\Service\UserAuth;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[OA\Tag(name: 'Group', description: 'Group management.')]
class GroupController extends BaseController
{
    private const TYPE_MANAGERS = 'managers';

    private const TYPE_MEMBERS = 'members';

    private UserAuth $userAuth;

    private Account $account;

    private AccountGroup $accountGroup;

    private string $namePattern = "/^[-._a-zA-Z\d]+$/";

    private ?Group $group = null;

    private ?Player $player = null;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        UserAuth $userAuth,
        Account $account,
        AccountGroup $accountGroup
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->userAuth = $userAuth;
        $this->account = $account;
        $this->accountGroup = $accountGroup;
    }

    #[OA\Get(
        path: '/user/group/all',
        operationId: 'userGroupAll',
        description: 'Needs role: app-admin, group-admin, watchlist-admin, plugin-admin or user-manager',
        summary: 'List all groups.',
        security: [['Session' => []]],
        tags: ['Group'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of groups.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Group')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function all(): ResponseInterface
    {
        return $this->withJson($this->repositoryFactory->getGroupRepository()->findBy([], ['name' => 'ASC']));
    }

    #[OA\Get(
        path: '/user/group/public',
        operationId: 'userGroupPublic',
        description: 'Needs role: user',
        summary: 'List all public groups that the player can join.',
        security: [['Session' => []]],
        tags: ['Group'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of groups.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Group')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function public(): ResponseInterface
    {
        $publicGroups = $this->repositoryFactory->getGroupRepository()->findBy(
            ['visibility' => Group::VISIBILITY_PUBLIC],
            ['name' => 'ASC']
        );

        // check required groups
        $groups = [];
        $player = $this->getUser($this->userAuth)->getPlayer();
        foreach ($publicGroups as $publicGroup) {
            if ($player->isAllowedMember($publicGroup)) {
                $groups[] = $publicGroup;
            }
        }

        return $this->withJson($groups);
    }

    #[OA\Post(
        path: '/user/group/create',
        operationId: 'userGroupCreate',
        description: 'Needs role: group-admin',
        summary: 'Create a group.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            description: 'Name of the group.',
                            type: 'string',
                            maxLength: 64,
                            pattern: '^[-._a-zA-Z0-9]+$'
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        tags: ['Group'],
        responses: [
            new OA\Response(
                response: '201',
                description: 'The new group.',
                content: new OA\JsonContent(ref: '#/components/schemas/Group')
            ),
            new OA\Response(response: '400', description: 'Group name is invalid.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '409', description: 'A group with this name already exists.')
        ],
    )]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $name = $this->getBodyParam($request, 'name', '');
        if (!preg_match($this->namePattern, $name)) {
            return $this->response->withStatus(400);
        }

        if ($this->otherGroupExists($name)) {
            return $this->response->withStatus(409);
        }

        $newGroup = new Group();
        $newGroup->setName($name);

        $this->objectManager->persist($newGroup);

        return $this->flushAndReturn(201, $newGroup);
    }

    #[OA\Put(
        path: '/user/group/{id}/rename',
        operationId: 'userGroupRename',
        description: 'Needs role: group-admin',
        summary: 'Rename a group.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            description: 'New name for the group.',
                            type: 'string',
                            maxLength: 64,
                            pattern: '^[-._a-zA-Z0-9]+$'
                        )
                    ],
                    type: 'object',
                )
            )
        ),
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Group was renamed.',
                content: new OA\JsonContent(ref: '#/components/schemas/Group')
            ),
            new OA\Response(response: '400', description: 'Group name is invalid.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.'),
            new OA\Response(response: '409', description: 'A group with this name already exists.')
        ],
    )]
    public function rename(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        $name = $this->getBodyParam($request, 'name', '');
        if (!preg_match($this->namePattern, $name)) {
            return $this->response->withStatus(400);
        }

        if ($this->otherGroupExists($name, $this->group->getId())) {
            return $this->response->withStatus(409);
        }

        $this->group->setName($name);

        return $this->flushAndReturn(200, $this->group);
    }

    #[OA\Put(
        path: '/user/group/{id}/update-description',
        operationId: 'userGroupUpdateDescription',
        description: 'Needs role: group-admin',
        summary: 'Update group description.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['description'],
                    properties: [
                        new OA\Property(
                            property: 'description',
                            description: 'The description for the group.',
                            type: 'string',
                            maxLength: 1024
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Description was updated.',
                content: new OA\JsonContent(ref: '#/components/schemas/Group')
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function updateDescription(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        $description = $this->getBodyParam($request, 'description', '');
        $this->group->setDescription($description);

        return $this->flushAndReturn(200, $this->group);
    }

    #[OA\Put(
        path: '/user/group/{id}/set-visibility/{choice}',
        operationId: 'setVisibility',
        description: 'Needs role: group-admin',
        summary: 'Change visibility of a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'choice',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['private', 'public'])
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Visibility changed.',
                content: new OA\JsonContent(ref: '#/components/schemas/Group')
            ),
            new OA\Response(response: '400', description: "Invalid 'choice' parameter."),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function setVisibility(string $id, string $choice): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        try {
            $this->group->setVisibility($choice);
        } catch (\Exception) {
            return $this->response->withStatus(400);
        }

        return $this->flushAndReturn(200, $this->group);
    }

    #[OA\Put(
        path: '/user/group/{id}/set-auto-accept/{choice}',
        operationId: 'userGroupSetAutoAccept',
        description: 'Needs role: group-admin',
        summary: 'Change the auto-accept setting of a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'choice',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['on', 'off'])
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Auto-accept changed.',
                content: new OA\JsonContent(ref: '#/components/schemas/Group')
            ),
            new OA\Response(response: '400', description: "Invalid 'choice' parameter."),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function setAutoAccept(string $id, string $choice): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        if (!in_array($choice, ['on', 'off'])) {
            return $this->response->withStatus(400);
        }

        $this->group->setAutoAccept($choice === 'on');

        return $this->flushAndReturn(200, $this->group);
    }

    #[OA\Put(
        path: '/user/group/{id}/set-is-default/{choice}',
        operationId: 'userGroupSetIsDefault',
        description: 'Needs role: group-admin',
        summary: 'Change the is-default setting of a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'choice',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['on', 'off'])
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Is-default changed.',
                content: new OA\JsonContent(ref: '#/components/schemas/Group')
            ),
            new OA\Response(response: '400', description: "Invalid 'choice' parameter."),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function setIsDefault(string $id, string $choice): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        if (!in_array($choice, ['on', 'off'])) {
            return $this->response->withStatus(400);
        }

        $this->group->setIsDefault($choice === 'on');

        return $this->flushAndReturn(200, $this->group);
    }

    #[OA\Delete(
        path: '/user/group/{id}/delete',
        operationId: 'userGroupDelete',
        description: 'Needs role: group-admin',
        summary: 'Delete a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group was deleted.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function delete(string $id): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        $this->objectManager->remove($this->group);

        return $this->flushAndReturn(204);
    }

    #[OA\Get(
        path: '/user/group/{id}/managers',
        operationId: 'userGroupManagers',
        description: 'Needs role: group-admin, group-manager',
        summary: 'List all managers of a group.',
        security: [['Session' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of players ordered by name. Only id and name, and roles for users with' .
                    ' group-admin role, properties are returned.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Player')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function managers(string $id): ResponseInterface
    {
        $user = $this->getUser($this->userAuth)->getPlayer();
        $withRole = $user->hasRole(Role::GROUP_ADMIN);

        return $this->getPlayersFromGroup($id, self::TYPE_MANAGERS, false, $withRole);
    }

    #[OA\Get(
        path: '/user/group/{id}/corporations',
        operationId: 'corporations',
        description: 'Needs role: group-admin',
        summary: 'List all corporations of a group.',
        security: [['Session' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of corporations ordered by name.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Corporation')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function corporations(string $id): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($this->group->getCorporations());
    }

    #[OA\Get(
        path: '/user/group/{id}/alliances',
        operationId: 'alliances',
        description: 'Needs role: group-admin',
        summary: 'List all alliances of a group.',
        security: [['Session' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of alliances ordered by name.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Alliance')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function alliances(string $id): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($this->group->getAlliances());
    }

    #[OA\Get(
        path: '/user/group/{id}/required-groups',
        operationId: 'requiredGroups',
        description: 'Needs role: group-admin, group-manager',
        summary: 'List all required groups of a group.',
        security: [['Session' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of groups ordered by name.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Group')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function requiredGroups(string $id): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($this->group->getRequiredGroups());
    }

    #[OA\Put(
        path: '/user/group/{id}/add-required/{groupId}',
        operationId: 'addRequiredGroup',
        description: 'Needs role: group-admin',
        summary: 'Add required group to a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'groupId',
                description: 'ID of the group to add.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group(s) not found.')
        ],
    )]
    public function addRequiredGroup(string $id, string $groupId): ResponseInterface
    {
        $requiredGroup = $this->repositoryFactory->getGroupRepository()->find((int)$groupId);
        $this->findGroup($id);
        if (!$this->group || !$requiredGroup) {
            return $this->response->withStatus(404);
        }

        $this->group->addRequiredGroup($requiredGroup);

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/group/{id}/remove-required/{groupId}',
        operationId: 'removeRequiredGroup',
        description: 'Needs role: group-admin',
        summary: 'Remove required group from a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'groupId',
                description: 'ID of the group to remove.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group(s) not found.')
        ],
    )]
    public function removeRequiredGroup(string $id, string $groupId): ResponseInterface
    {
        $requiredGroup = $this->repositoryFactory->getGroupRepository()->find((int)$groupId);
        $this->findGroup($id);
        if (!$this->group || !$requiredGroup) {
            return $this->response->withStatus(404);
        }

        $this->group->removeRequiredGroup($requiredGroup);

        return $this->flushAndReturn(204);
    }

    #[OA\Get(
        path: '/user/group/{id}/forbidden-groups',
        operationId: 'userGroupForbiddenGroups',
        description: 'Needs role: group-admin, group-manager',
        summary: 'List all forbidden groups of a group.',
        security: [['Session' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of groups ordered by name.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Group')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function forbiddenGroups(string $id): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($this->group->getForbiddenGroups());
    }

    #[OA\Put(
        path: '/user/group/{id}/add-forbidden/{groupId}',
        operationId: 'userGroupAddForbiddenGroup',
        description: 'Needs role: group-admin',
        summary: 'Add forbidden group to a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'groupId',
                description: 'ID of the group to add.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group(s) not found.')
        ],
    )]
    public function addForbiddenGroup(string $id, string $groupId): ResponseInterface
    {
        $forbiddenGroup = $this->repositoryFactory->getGroupRepository()->find((int)$groupId);
        $this->findGroup($id);
        if (!$this->group || !$forbiddenGroup) {
            return $this->response->withStatus(404);
        }

        $this->group->addForbiddenGroup($forbiddenGroup);

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/group/{id}/remove-forbidden/{groupId}',
        operationId: 'userGroupRemoveForbiddenGroup',
        description: 'Needs role: group-admin',
        summary: 'Remove forbidden group from a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'groupId',
                description: 'ID of the group to remove.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group(s) not found.')
        ],
    )]
    public function removeForbiddenGroup(string $id, string $groupId): ResponseInterface
    {
        $forbiddenGroup = $this->repositoryFactory->getGroupRepository()->find((int)$groupId);
        $this->findGroup($id);
        if (!$this->group || !$forbiddenGroup) {
            return $this->response->withStatus(404);
        }

        $this->group->removeForbiddenGroup($forbiddenGroup);

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/group/{id}/add-manager/{pid}',
        operationId: 'userGroupAddManager',
        description: 'Needs role: group-admin',
        summary: 'Assign a player as manager to a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'pid',
                description: 'ID of the player.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Player added as manager.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Player and/or group not found.')
        ],
    )]
    public function addManager(string $id, string $pid): ResponseInterface
    {
        return $this->addPlayerAs($id, $pid, 'manager', false);
    }

    #[OA\Put(
        path: '/user/group/{id}/remove-manager/{pid}',
        operationId: 'userGroupRemoveManager',
        description: 'Needs role: group-admin',
        summary: 'Remove a manager (player) from a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'pid',
                description: 'ID of the player.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Player removed from managers.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Player and/or group not found.')
        ],
    )]
    public function removeManager(string $id, string $pid): ResponseInterface
    {
        return $this->removePlayerFrom($id, $pid, self::TYPE_MANAGERS, false);
    }

    #[OA\Get(
        path: '/user/group/{id}/applications',
        operationId: 'applications',
        description: 'Needs role: group-manager',
        summary: 'List all applications of a group.',
        security: [['Session' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of group applications ordered by created date.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/GroupApplication')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function applications(string $id): ResponseInterface
    {
        $this->findGroup($id);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        if (!$this->checkManager($this->group)) {
            return $this->response->withStatus(403);
        }

        $apps = $this->repositoryFactory->getGroupApplicationRepository()->findBy(['group' => $id]);

        return $this->withJson($apps);
    }

    #[OA\Put(
        path: '/user/group/accept-application/{id}',
        operationId: 'acceptApplication',
        description: 'Needs role: group-manager',
        summary: "Accept a player's request to join a group.",
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the application.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Application accepted.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Application not found.'),
            new OA\Response(response: '400', description: 'Player is not allowed to be a member of the group.')
        ],
    )]
    public function acceptApplication(string $id): ResponseInterface
    {
        return $this->handleApplication($id, 'accept');
    }

    #[OA\Put(
        path: '/user/group/deny-application/{id}',
        operationId: 'denyApplication',
        description: 'Needs role: group-manager',
        summary: "Deny a player's request to join a group.",
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the application.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Application denied.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Application not found.')
        ],
    )]
    public function denyApplication(string $id): ResponseInterface
    {
        return $this->handleApplication($id, 'deny');
    }

    #[OA\Put(
        path: '/user/group/{id}/add-member/{pid}',
        operationId: 'addMember',
        description: 'Needs role: group-manager or user-manager',
        summary: 'Adds a player to a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'pid',
                description: 'ID of the player.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Player added.'),
            new OA\Response(
                response: '400',
                description: 'This player is not a member of one of the required groups.'
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Player and/or group not found.')
        ],
    )]
    public function addMember(string $id, string $pid): ResponseInterface
    {
        return $this->addPlayerAs($id, $pid, 'member', true);
    }

    #[OA\Put(
        path: '/user/group/{id}/remove-member/{pid}',
        operationId: 'removeMember',
        description: 'Needs role: group-manager or user-manager',
        summary: 'Remove player from a group.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'pid',
                description: 'ID of the player.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Player removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Player and/or group not found.')
        ],
    )]
    public function removeMember(string $id, string $pid): ResponseInterface
    {
        return $this->removePlayerFrom($id, $pid, self::TYPE_MEMBERS, true);
    }

    #[OA\Get(
        path: '/user/group/{id}/members',
        operationId: 'userGroupMembers',
        description: 'Needs role: group-admin, group-manager',
        summary: 'List all members of a group.',
        security: [['Session' => []]],
        tags: ['Group'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of players ordered by name.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Player')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group not found.')
        ],
    )]
    public function members(string $id): ResponseInterface
    {
        $user = $this->getUser($this->userAuth)->getPlayer();
        $onlyIfManager = !$user->hasRole(Role::GROUP_ADMIN);

        return $this->getPlayersFromGroup($id, self::TYPE_MEMBERS, $onlyIfManager, false);
    }

    /**
     * Returns true if another group with that name already exists.
     *
     * @param string $name Group name.
     * @param int|null $id Group ID.
     * @return boolean
     */
    private function otherGroupExists(string $name, int $id = null): bool
    {
        $otherGroup = $this->repositoryFactory->getGroupRepository()->findOneBy(['name' => $name]);

        if ($otherGroup === null) {
            return false;
        }

        if ($otherGroup->getId() === $id) {
            return false;
        }

        return true;
    }

    private function getPlayersFromGroup(
        string $groupId,
        string $type,
        bool $onlyIfManager,
        bool $withRoles
    ): ResponseInterface {
        $this->findGroup($groupId);
        if (!$this->group) {
            return $this->response->withStatus(404);
        }

        if ($onlyIfManager && !$this->checkManager($this->group)) {
            return $this->response->withStatus(403);
        }

        $players = [];
        if ($type === self::TYPE_MANAGERS) {
            $players = $this->group->getManagers();
        } elseif ($type === self::TYPE_MEMBERS) {
            $players = $this->repositoryFactory->getGroupRepository()
                ->getMembersWithCorporationAndAlliance($this->group->getId());
        }

        $ret = [];
        foreach ($players as $player) {
            if ($player instanceof Player) {
                $result = $player->jsonSerialize(true);
            } else { // result from getMembersWithCorporationAndAlliance()
                $result = [
                    'id' => $player['player_id'],
                    'name' => $player['player_name'],
                    'characterId' => $player['character_id'],
                    'corporationName' => $player['corporation_name'],
                    'allianceName' => $player['alliance_name'],
                ];
            }
            if ($withRoles) {
                $result['roles'] = $player->getRoles();
            }
            $ret[] = $result;
        }

        return $this->withJson($ret);
    }

    private function addPlayerAs(
        string $groupId,
        string $playerId,
        string $type,
        bool $onlyIfManager
    ): ResponseInterface {
        $this->findGroupAndPlayer($groupId, $playerId);
        if (!$this->group || !$this->player) {
            return $this->response->withStatus(404);
        }

        if ($onlyIfManager && !$this->checkManager($this->group) && !$this->isUserManager()) {
            return $this->response->withStatus(403);
        }

        if ($type === 'manager' && !$this->player->hasManagerGroup($this->group->getId())) {
            if (!$this->account->mayHaveRole($this->player, Role::GROUP_MANAGER)) {
                return $this->response->withStatus(400);
            }
            $this->group->addManager($this->player); // needed to persist
            $this->player->addManagerGroup($this->group); // needed for check in syncManagerRole()
            $this->account->syncManagerRole($this->player, Role::GROUP_MANAGER);
        } elseif ($type === 'member' && !$this->player->hasGroup($this->group->getId())) {
            if (!$this->player->isAllowedMember($this->group)) {
                return $this->response->withStatus(400);
            }
            $this->player->addGroup($this->group);
            $this->account->updateGroups($this->player->getId());
        }

        return $this->flushAndReturn(204);
    }

    private function removePlayerFrom(
        string $groupId,
        string $playerId,
        string $type,
        bool $onlyIfManager
    ): ResponseInterface {
        $this->findGroupAndPlayer($groupId, $playerId);
        if (!$this->group || !$this->player) {
            return $this->response->withStatus(404);
        }

        if ($onlyIfManager && !$this->checkManager($this->group) && !$this->isUserManager()) {
            return $this->response->withStatus(403);
        }

        if ($type === self::TYPE_MANAGERS) {
            $this->group->removeManager($this->player); // needed to persist
            $this->player->removeManagerGroup($this->group); // needed for check in syncManagerRole()
            $this->account->syncManagerRole($this->player, Role::GROUP_MANAGER);
        } elseif ($type === self::TYPE_MEMBERS) {
            $this->accountGroup->removeGroupAndApplication($this->player, $this->group);
            $this->account->updateGroups($this->player->getId());
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @param string $id application ID
     * @param string $action "accept" or "deny"
     * @return ResponseInterface
     */
    private function handleApplication(string $id, string $action): ResponseInterface
    {
        $app = $this->repositoryFactory->getGroupApplicationRepository()->find($id);

        if (!$app) {
            return $this->response->withStatus(404);
        }

        $group = $app->getGroup();

        if (!$this->checkManager($group)) {
            return $this->response->withStatus(403);
        }

        if ($action === 'accept') {
            $player = $app->getPlayer();
            if (!$player->isAllowedMember($group)) {
                return $this->response->withStatus(400);
            }
            $app->setStatus(GroupApplication::STATUS_ACCEPTED);
            if (!$player->hasGroup($group->getId())) {
                $player->addGroup($group);
                $this->account->syncTrackingRole($player);
                $this->account->syncWatchlistRole($player);
                $this->account->syncWatchlistManagerRole($player);
            }
        } elseif ($action === 'deny') {
            $app->setStatus(GroupApplication::STATUS_DENIED);
        }

        return $this->flushAndReturn(204);
    }

    private function findGroup(string $id): bool
    {
        $groupEntity = $this->repositoryFactory->getGroupRepository()->find((int)$id);
        if ($groupEntity === null) {
            return false;
        }
        $this->group = $groupEntity;

        return true;
    }

    private function findGroupAndPlayer(string $groupId, string $playerId): void
    {
        $playerEntity = $this->repositoryFactory->getPlayerRepository()->find((int)$playerId);
        if (!$this->findGroup($groupId) || $playerEntity === null) {
            return;
        }
        $this->player = $playerEntity;
    }

    /**
     * Checks if current logged-in user is manager of a group.
     *
     * @param Group $group
     * @return boolean
     */
    private function checkManager(Group $group): bool
    {
        $currentPlayer = $this->getUser($this->userAuth)->getPlayer();
        foreach ($currentPlayer->getManagerGroups() as $mg) {
            if ($mg->getId() === $group->getId()) {
                return true;
            }
        }

        return false;
    }

    private function isUserManager(): bool
    {
        return $this->getUser($this->userAuth)->getPlayer()->hasRole(Role::USER_MANAGER);
    }
}
