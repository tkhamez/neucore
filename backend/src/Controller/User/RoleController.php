<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Group;
use Neucore\Entity\Role;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

#[OA\Tag(name: 'Role', description: 'Role management.')]
class RoleController extends BaseController
{
    private ?Role $role = null;

    private ?Group $group = null;

    #[OA\Get(
        path: '/user/role/{roleName}/required-groups',
        operationId: 'userRoleRequiredGroups',
        description: 'Needs role: user-admin',
        summary: 'List all required groups of a role.',
        security: [['Session' => []]],
        tags: ['Role'],
        parameters: [
            new OA\Parameter(
                name: 'roleName',
                description: 'Role name.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of required groups.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Group')),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Role not found.'),
        ],
    )]
    public function getRequiredGroups(string $roleName): ResponseInterface
    {
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $roleName]);

        if (!$role) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($role->getRequiredGroups());
    }

    #[OA\Put(
        path: '/user/role/{roleName}/add-required-group/{groupId}',
        operationId: 'userRoleAddRequiredGroup',
        description: 'Needs role: user-admin',
        summary: 'Add a group as a requirement to the role.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Role'],
        parameters: [
            new OA\Parameter(
                name: 'roleName',
                description: 'Name of the role.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
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
            new OA\Response(response: '403', description: 'Not authorized or role not allowed.'),
            new OA\Response(response: '404', description: 'Role and/or group not found.'),
        ],
    )]
    public function addRequiredGroups(string $roleName, string $groupId): ResponseInterface
    {
        $response = $this->fetchEntitiesAndValidate($roleName, (int) $groupId);
        if ($response) {
            return $response;
        }

        if ($this->role && $this->group) {
            $this->role->addRequiredGroup($this->group);
        }

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/role/{roleName}/remove-required-group/{groupId}',
        operationId: 'userRoleRemoveRequiredGroup',
        description: 'Needs role: user-admin',
        summary: 'Remove a group from being a requirement from the role.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Role'],
        parameters: [
            new OA\Parameter(
                name: 'roleName',
                description: 'Name of the role.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
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
            new OA\Response(response: '404', description: 'Role and/or group not found.'),
        ],
    )]
    public function removeRequiredGroups(string $roleName, string $groupId): ResponseInterface
    {
        $response = $this->fetchEntitiesAndValidate($roleName, (int) $groupId);
        if ($response) {
            return $response;
        }

        if ($this->role && $this->group) {
            $this->role->removeRequiredGroup($this->group);
        }

        return $this->flushAndReturn(204);
    }

    private function fetchEntitiesAndValidate(string $roleName, int $groupId): ?ResponseInterface
    {
        $this->role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $roleName]);
        $this->group = $this->repositoryFactory->getGroupRepository()->find($groupId);

        if (!$this->role || !$this->group) {
            return $this->response->withStatus(404);
        }

        if (!in_array($this->role->getName(), Role::ROLES_WITH_GROUP_REQUIREMENT)) {
            return $this->response->withStatus(403);
        }

        return null;
    }
}
