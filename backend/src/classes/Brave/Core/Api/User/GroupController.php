<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Api\BaseController;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\GroupApplication;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\Role;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\ObjectManager;
use Brave\Core\Service\UserAuth;
use Slim\Http\Request;
use Slim\Http\Response;
use Swagger\Annotations as SWG;

/**
 * @SWG\Tag(
 *     name="Group",
 *     description="Group management."
 * )
 */
class GroupController extends BaseController
{
    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var UserAuth
     */
    private $userAuth;

    /**
     * @var string
     */
    private $namePattern = "/^[-._a-zA-Z0-9]+$/";

    /**
     * @var Group
     */
    private $group;

    /**
     * @var Player
     */
    private $player;

    public function __construct(
        Response $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        UserAuth $userAuth
    ) {
        parent::__construct($response, $objectManager);

        $this->repositoryFactory = $repositoryFactory;
        $this->userAuth = $userAuth;
    }

    /**
     * @SWG\Get(
     *     path="/user/group/all",
     *     operationId="all",
     *     summary="List all groups.",
     *     description="Needs role: app-admin, group-admin or user-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function all(): Response
    {
        return $this->response->withJson($this->repositoryFactory->getGroupRepository()->findBy([], ['name' => 'ASC']));
    }

    /**
     * @SWG\Get(
     *     path="/user/group/public",
     *     operationId="public",
     *     summary="List all public groups.",
     *     description="Needs role: user",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function public(): Response
    {
        return $this->response->withJson($this->repositoryFactory->getGroupRepository()->findBy(
            ['visibility' => Group::VISIBILITY_PUBLIC],
            ['name' => 'ASC']
        ));
    }

    /**
     * @SWG\Post(
     *     path="/user/group/create",
     *     operationId="create",
     *     summary="Create a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     consumes={"application/x-www-form-urlencoded"},
     *     @SWG\Parameter(
     *         name="name",
     *         in="formData",
     *         required=true,
     *         description="Name of the group.",
     *         type="string",
     *         maxLength=64,
     *         pattern="^[-._a-zA-Z0-9]+$"
     *     ),
     *     @SWG\Response(
     *         response="201",
     *         description="The new group.",
     *         @SWG\Schema(ref="#/definitions/Group")
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Group name is invalid."
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="A group with this name already exists."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function create(Request $request): Response
    {
        $name = $request->getParam('name', '');
        if (! preg_match($this->namePattern, $name)) {
            return $this->response->withStatus(400);
        }

        if ($this->otherGroupExists($name)) {
            return $this->response->withStatus(409);
        }

        $group = new Group();
        $group->setName($name);

        $this->objectManager->persist($group);

        return $this->flushAndReturn(201, $group);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/rename",
     *     operationId="rename",
     *     summary="Rename a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     consumes={"application/x-www-form-urlencoded"},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="name",
     *         in="formData",
     *         required=true,
     *         description="New name for the group.",
     *         type="string",
     *         maxLength=64,
     *         pattern="^[-._a-zA-Z0-9]+$"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Group was renamed.",
     *         @SWG\Schema(ref="#/definitions/Group")
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group not found."
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Group name is invalid."
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="A group with this name already exists."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function rename(string $id, Request $request): Response
    {
        if (! $this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        $name = $request->getParam('name', '');
        if (! preg_match($this->namePattern, $name)) {
            return $this->response->withStatus(400);
        }

        if ($this->otherGroupExists($name, $this->group->getId())) {
            return $this->response->withStatus(409);
        }

        $this->group->setName($name);

        return $this->flushAndReturn(200, $this->group);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/set-visibility/{choice}",
     *     operationId="setVisibility",
     *     summary="Change visibility of a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="choice",
     *         in="path",
     *         required=true,
     *         type="string",
     *         enum={"private", "public", "conditioned"}
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Visibility changed.",
     *         @SWG\Schema(ref="#/definitions/Group")
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid 'choice' parameter."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function setVisibility(string $id, string $choice): Response
    {
        if (! $this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        try {
            $this->group->setVisibility($choice);
        } catch (\Exception $e) {
            return $this->response->withStatus(400);
        }

        return $this->flushAndReturn(200, $this->group);
    }

    /**
     * @SWG\Delete(
     *     path="/user/group/{id}/delete",
     *     operationId="delete",
     *     summary="Delete a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Group was deleted."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function delete(string $id): Response
    {
        if (! $this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        $this->objectManager->remove($this->group);

        return $this->flushAndReturn(204);
    }

    /**
     * @SWG\Get(
     *     path="/user/group/{id}/managers",
     *     operationId="managers",
     *     summary="List all managers of a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id, name and roles properties are returned.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Player"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function managers(string $id): Response
    {
        return $this->getPlayersFromGroup($id, 'managers', false);
    }

    /**
     * @SWG\Get(
     *     path="/user/group/{id}/corporations",
     *     operationId="corporations",
     *     summary="List all corporations of a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of corporations ordered by name.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Corporation"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corporations(string $id): Response
    {
        if (! $this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        return $this->response->withJson($this->group->getCorporations());
    }

    /**
     * @SWG\Get(
     *     path="/user/group/{id}/alliances",
     *     operationId="alliances",
     *     summary="List all alliances of a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of alliances ordered by name.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Alliance"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function alliances(string $id): Response
    {
        if (! $this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        return $this->response->withJson($this->group->getAlliances());
    }

    /**
     * @SWG\Get(
     *     path="/user/group/{id}/required-groups",
     *     operationId="requiredGroups",
     *     summary="List all required groups of a group.",
     *     description="Needs role: group-admin, group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups ordered by name.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function requiredGroups(string $id): Response
    {
        $group = $this->repositoryFactory->getGroupRepository()->find((int) $id);
        if (! $group) {
            return $this->response->withStatus(404, 'Group not found.');
        }

        return $this->response->withJson($group->getRequiredGroups());
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/add-required/{groupId}",
     *     operationId="addRequiredGroup",
     *     summary="Add required group to a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="ID of the group to add.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Group added."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group(s) not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function addRequiredGroup(string $id, string $groupId): Response
    {
        $group = $this->repositoryFactory->getGroupRepository()->find((int) $id);
        $requiredGroup = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);

        if (! $group || ! $requiredGroup) {
            return $this->response->withStatus(404, 'Group(s) not found.');
        }

        $hasGroup = false;
        foreach ($group->getRequiredGroups() as $existingGroup) {
            if ($existingGroup->getId() === (int) $groupId) {
                $hasGroup = true;
                break;
            }
        }
        if (! $hasGroup) {
            $group->addRequiredGroup($requiredGroup);
        }

        return $this->flushAndReturn(204, $group);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/remove-required/{groupId}",
     *     operationId="removeRequiredGroup",
     *     summary="Remove required group from a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="ID of the group to remove.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Group removed."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group(s) not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function removeRequiredGroup(string $id, string $groupId): Response
    {
        $group = $this->repositoryFactory->getGroupRepository()->find((int) $id);

        if (! $group) {
            return $this->response->withStatus(404, 'Group not found.');
        }

        $removed = false;
        foreach ($group->getRequiredGroups() as $requiredGroup) {
            if ($requiredGroup->getId() === (int) $groupId) {
                $group->removeRequiredGroup($requiredGroup);
                $removed = true;
                break;
            }
        }

        if (! $removed) {
            return $this->response->withStatus(404, 'Group not found.');
        }

        return $this->flushAndReturn(204, $group);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/add-manager/{pid}",
     *     operationId="addManager",
     *     summary="Assign a player as manager to a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="pid",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Player added as manager."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Player and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function addManager(string $id, string $pid): Response
    {
        return $this->addPlayerAs($id, $pid, 'manager', false);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/remove-manager/{pid}",
     *     operationId="removeManager",
     *     summary="Remove a manager (player) from a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="pid",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Player removed from managers."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Player and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function removeManager(string $id, string $pid): Response
    {
        return $this->removePlayerFrom($id, $pid, 'managers', false);
    }

    /**
     * @SWG\Get(
     *     path="/user/group/{id}/applications",
     *     operationId="applications",
     *     summary="List all applications of a group.",
     *     description="Needs role: group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of group applications ordered by created date.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/GroupApplication"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function applications(string $id): Response
    {
        if (! $this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        if (! $this->checkManager($this->group)) {
            return $this->response->withStatus(403);
        }

        $apps = $this->repositoryFactory->getGroupApplicationRepository()->findBy(['group' => $id]);

        return $this->response->withJson($apps);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/accept-application/{id}",
     *     operationId="acceptApplication",
     *     summary="Accept a player's request to join a group.",
     *     description="Needs role: group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the application.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Application accepted."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Application not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function acceptApplication(string $id): Response
    {
        return $this->handleApplication($id, 'accept');
    }

    /**
     * @SWG\Put(
     *     path="/user/group/deny-application/{id}",
     *     operationId="denyApplication",
     *     summary="Deny a player's request to join a group.",
     *     description="Needs role: group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the application.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Application denied."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Application not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function denyApplication(string $id): Response
    {
        return $this->handleApplication($id, 'deny');
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/add-member/{pid}",
     *     operationId="addMember",
     *     summary="Adds a player to a group.",
     *     description="Needs role: group-manager or user-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="pid",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Player added."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Player and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function addMember(string $id, string $pid): Response
    {
        return $this->addPlayerAs($id, $pid, 'member', true);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/remove-member/{pid}",
     *     operationId="removeMember",
     *     summary="Remove player from a group.",
     *     description="Needs role: group-manager or user-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="pid",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Player removed."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Player and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function removeMember(string $id, string $pid): Response
    {
        return $this->removePlayerFrom($id, $pid, 'members', true);
    }

    /**
     * @SWG\Get(
     *     path="/user/group/{id}/members",
     *     operationId="members",
     *     summary="List all members of a group.",
     *     description="Needs role: group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Player"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function members(string $id): Response
    {
        return $this->getPlayersFromGroup($id, 'members', true);
    }

    /**
     * Returns true if another group with that name already exists.
     *
     * @param string $name Group name.
     * @param int $id Group ID.
     * @return boolean
     */
    private function otherGroupExists(string $name, int $id = null): bool
    {
        $group = $this->repositoryFactory->getGroupRepository()->findOneBy(['name' => $name]);

        if ($group === null) {
            return false;
        }

        if ($group->getId() === $id) {
            return false;
        }

        return true;
    }

    private function getPlayersFromGroup(string $groupId, string $type, bool $onlyIfManager): Response
    {
        if (! $this->findGroup($groupId)) {
            return $this->response->withStatus(404);
        }

        if ($onlyIfManager && ! $this->checkManager($this->group)) {
            return $this->response->withStatus(403);
        }

        $players = [];
        if ($type === 'managers') {
            $players = $this->group->getManagers();
        } elseif ($type === 'members') {
            $players = $this->group->getPlayers();
        }

        $ret = [];
        foreach ($players as $player) {
            $result = $player->jsonSerialize(true);
            if ($type === 'managers') {
                $result['roles'] = $player->getRoles();
            }
            $ret[] = $result;
        }

        return $this->response->withJson($ret);
    }

    private function addPlayerAs(
        string $groupId,
        string $playerId,
        string $type,
        bool $onlyIfManagerOrAdmin
    ): Response {
        if (! $this->findGroupAndPlayer($groupId, $playerId)) {
            return $this->response->withStatus(404);
        }

        if ($onlyIfManagerOrAdmin && ! $this->checkManager($this->group) && ! $this->isUserManager()) {
            return $this->response->withStatus(403);
        }

        if ($type === 'manager' && ! $this->player->hasManagerGroup($this->group)) {
            $this->group->addManager($this->player);
        } elseif ($type === 'member' && ! $this->player->hasGroup($this->group->getId())) {
            $this->player->addGroup($this->group);
        }

        return $this->flushAndReturn(204);
    }

    private function removePlayerFrom(
        string $groupId,
        string $playerId,
        string $type,
        bool $onlyIfManagerOrAdmin
    ): Response {
        if (! $this->findGroupAndPlayer($groupId, $playerId)) {
            return $this->response->withStatus(404);
        }

        if ($onlyIfManagerOrAdmin && ! $this->checkManager($this->group) && ! $this->isUserManager()) {
            return $this->response->withStatus(403);
        }

        if ($type === 'managers') {
            $this->group->removeManager($this->player);
        } elseif ($type === 'members') {
            $this->player->removeGroup($this->group);
        } elseif ($type === 'applications') {
            $apps = $this->repositoryFactory->getGroupApplicationRepository()
                ->findBy(['player' => $playerId, 'group' => $groupId]);
            foreach ($apps as $groupApplication) {
                $this->objectManager->remove($groupApplication);
            }
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @param string $id application ID
     * @param string $action "accept" or "deny"
     * @return Response
     */
    private function handleApplication(string $id, string $action): Response
    {
        $app = $this->repositoryFactory->getGroupApplicationRepository()->find($id);

        if (! $app || ! $app->getGroup() || ! $app->getPlayer()) {
            return $this->response->withStatus(404);
        }

        if (! $this->checkManager($app->getGroup())) {
            return $this->response->withStatus(403);
        }

        if ($action === 'accept') {
            $app->setStatus(GroupApplication::STATUS_ACCEPTED);
            if (! $app->getPlayer()->hasGroup($app->getGroup()->getId())) {
                $app->getPlayer()->addGroup($app->getGroup());
            }
        } elseif ($action === 'deny') {
            $app->setStatus(GroupApplication::STATUS_DENIED);
        }

        return $this->flushAndReturn(204);
    }

    private function findGroup(string $id): bool
    {
        $this->group = $this->repositoryFactory->getGroupRepository()->find((int) $id);
        if ($this->group === null) {
            return false;
        }

        return true;
    }

    private function findGroupAndPlayer(string $groupId, string $playerId): bool
    {
        $this->group = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);
        $this->player = $this->repositoryFactory->getPlayerRepository()->find((int) $playerId);

        if ($this->group === null || $this->player === null) {
            return false;
        }

        return true;
    }

    /**
     * Checks if current logged in user is manager of a group.
     *
     * @param Group $group
     * @return boolean
     */
    private function checkManager(Group $group): bool
    {
        $currentPlayer = $this->userAuth->getUser()->getPlayer();
        foreach ($currentPlayer->getManagerGroups() as $mg) {
            if ($mg->getId() === $group->getId()) {
                return true;
            }
        }

        return false;
    }

    private function isUserManager(): bool
    {
        return $this->userAuth->getUser()->getPlayer()->hasRole(Role::USER_MANAGER);
    }
}
