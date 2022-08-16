<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */

use Neucore\Entity\Group;
use Neucore\Entity\Role;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * @OA\Tag(
 *     name="Role",
 *     description="Role management."
 * )
 */
class RoleController extends BaseController
{
    private ?Role $role = null;

    private ?Group $group = null;

    /**
     * @OA\Get(
     *     path="/user/role/{roleName}/required-groups",
     *     operationId="userRoleRequiredGroups",
     *     summary="List all required groups of a role.",
     *     description="Needs role: user-admin",
     *     tags={"Role"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="roleName",
     *         in="path",
     *         required=true,
     *         description="Role name.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of required groups.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Group"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Role not found."
     *     )
     * )
     */
    public function getRequiredGroups(string $roleName): ResponseInterface
    {
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $roleName]);

        if (!$role) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($role->getRequiredGroups());
    }

    /**
     * @OA\Put(
     *     path="/user/role/{roleName}/add-required-group/{groupId}",
     *     operationId="userRoleAddRequiredGroup",
     *     summary="Add a group as a requirement to the role.",
     *     description="Needs role: user-admin",
     *     tags={"Role"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="roleName",
     *         in="path",
     *         required=true,
     *         description="Name of the role.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized or role not allowed."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Role and/or group not found."
     *     )
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/user/role/{roleName}/remove-required-group/{groupId}",
     *     operationId="userRoleRemoveRequiredGroup",
     *     summary="Remove a group from being a requirement from the role.",
     *     description="Needs role: user-admin",
     *     tags={"Role"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="roleName",
     *         in="path",
     *         required=true,
     *         description="Name of the role.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Role and/or group not found."
     *     )
     * )
     */
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

        if (!in_array($this->role->getName(), Role::$rolesWithGroupRequirement)) {
            return $this->response->withStatus(403);
        }

        return null;
    }
}
