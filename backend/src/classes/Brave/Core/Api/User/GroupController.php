<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Entity\Group;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\ObjectManager;
use Brave\Core\Service\UserAuth;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @SWG\Tag(
 *     name="Group",
 *     description="Group management."
 * )
 */
class GroupController
{
    /**
     * @var Response
     */
    private $res;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var UserAuth
     */
    private $uas;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var string
     */
    private $namePattern = "/^[-._a-zA-Z0-9]+$/";

    /**
     * @var \Brave\Core\Entity\Group
     */
    private $group;

    /**
     * @var \Brave\Core\Entity\Player
     */
    private $player;

    public function __construct(
        Response $res,
        RepositoryFactory $repositoryFactory,
        UserAuth $uas,
        ObjectManager $objectManager
    ) {
        $this->res = $res;
        $this->repositoryFactory = $repositoryFactory;
        $this->uas = $uas;
        $this->objectManager = $objectManager;
    }

    /**
     * @SWG\Get(
     *     path="/user/group/all",
     *     operationId="all",
     *     summary="List all groups.",
     *     description="Needs role: app-admin or group-admin",
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
        return $this->res->withJson($this->repositoryFactory->getGroupRepository()->findBy([], ['name' => 'ASC']));
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
        return $this->res->withJson($this->repositoryFactory->getGroupRepository()->findBy(
            ['visibility' => Group::VISIBILITY_PUBLIC], ['name' => 'ASC']));
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
            return $this->res->withStatus(400);
        }

        if ($this->otherGroupExists($name)) {
            return $this->res->withStatus(409);
        }

        $group = new Group();
        $group->setName($name);

        $this->objectManager->persist($group);
        if (! $this->objectManager->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(201)->withJson($group);
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
            return $this->res->withStatus(404);
        }

        $name = $request->getParam('name', '');
        if (! preg_match($this->namePattern, $name)) {
            return $this->res->withStatus(400);
        }

        if ($this->otherGroupExists($name, $this->group->getId())) {
            return $this->res->withStatus(409);
        }

        $this->group->setName($name);
        if (! $this->objectManager->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withJson($this->group);
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
            return $this->res->withStatus(404);
        }

        try {
            $this->group->setVisibility($choice);
        } catch (\Exception $e) {
            return $this->res->withStatus(400);
        }

        if (! $this->objectManager->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withJson($this->group);
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
            return $this->res->withStatus(404);
        }

        $this->objectManager->remove($this->group);
        if (! $this->objectManager->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
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
            return $this->res->withStatus(404);
        }

        $corps = $this->group->getCorporations();

        return $this->res->withJson($corps);
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
            return $this->res->withStatus(404);
        }

        $allis = $this->group->getAlliances();

        return $this->res->withJson($allis);
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
     *     path="/user/group/{id}/applicants",
     *     operationId="applicants",
     *     summary="List all applicants of a group.",
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
    public function applicants(string $id): Response
    {
        return $this->getPlayersFromGroup($id, 'applicants', true);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/remove-applicant/{pid}",
     *     operationId="removeApplicant",
     *     summary="Remove a player's request to join a group.",
     *     description="Needs role: group-manager",
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
     *         description="Application removed."
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
    public function removeApplicant(string $id, string $pid): Response
    {
        return $this->removePlayerFrom($id, $pid, 'applications', true);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/add-member/{pid}",
     *     operationId="addMember",
     *     summary="Adds a player to a group.",
     *     description="Needs role: group-manager",
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
     *     description="Needs role: group-manager",
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
            return $this->res->withStatus(404);
        }

        if ($onlyIfManager && ! $this->checkManager($this->group)) {
            return $this->res->withStatus(403);
        }

        $players = [];
        if ($type === 'managers') {
            $players = $this->group->getManagers();
        } elseif ($type === 'applicants') {
            $players = $this->group->getApplicants();
        } elseif ($type === 'members') {
            $players = $this->group->getPlayers();
        }

        $ret = [];
        foreach ($players as $player) {
            $ret[] = [
                'id' => $player->getId(),
                'name' => $player->getName()
            ];
        }

        return $this->res->withJson($ret);
    }

    private function addPlayerAs(string $groupId, string $playerId, string $type, bool $onlyIfManager): Response
    {
        if (! $this->findGroupAndPlayer($groupId, $playerId)) {
            return $this->res->withStatus(404);
        }

        if ($onlyIfManager && ! $this->checkManager($this->group)) {
            return $this->res->withStatus(403);
        }

        if ($type === 'manager' && ! $this->player->hasManagerGroup($this->group)) {
            $this->group->addManager($this->player);
        } elseif ($type === 'member' && ! $this->player->hasGroup($this->group->getId())) {
            $this->player->addGroup($this->group);
        }

        if (! $this->objectManager->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
    }

    private function removePlayerFrom(string $groupId, string $playerId, string $type, bool $onlyIfManager): Response
    {
        if (! $this->findGroupAndPlayer($groupId, $playerId)) {
            return $this->res->withStatus(404);
        }

        if ($onlyIfManager && ! $this->checkManager($this->group)) {
            return $this->res->withStatus(403);
        }

        if ($type === 'managers') {
            $this->group->removeManager($this->player);
        } elseif ($type === 'members') {
            $this->player->removeGroup($this->group);
        } elseif ($type === 'applications') {
            $this->player->removeApplication($this->group);
        }

        if (! $this->objectManager->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
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
        $currentPlayer = $this->uas->getUser()->getPlayer();
        foreach ($currentPlayer->getManagerGroups() as $mg) {
            if ($mg->getId() === $group->getId()) {
                return true;
            }
        }

        return false;
    }
}
