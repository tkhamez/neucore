<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Entity\Group;
use Brave\Core\Entity\GroupRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\PlayerRepository;
use Brave\Core\Service\UserAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var GroupRepository
     */
    private $gr;

    /**
     * @var PlayerRepository
     */
    private $pr;

    /**
     * @var UserAuthService
     */
    private $uas;

    /**
     * @var EntityManagerInterface
     */
    private $em;

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

    public function __construct(Response $res, LoggerInterface $log, GroupRepository $gr,
        PlayerRepository $pr, UserAuthService $uas, EntityManagerInterface $em)
    {
        $this->log = $log;
        $this->res = $res;
        $this->gr = $gr;
        $this->pr = $pr;
        $this->uas = $uas;
        $this->em = $em;
    }

    /**
     * @SWG\Get(
     *     path="/user/group/all",
     *     operationId="all",
     *     summary="List all groups.",
     *     description="Needs role: app-admin, group-admin or user-admin",
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
        return $this->res->withJson($this->gr->findBy([], ['name' => 'ASC']));
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
        return $this->res->withJson($this->gr->findBy(['public' => true], ['name' => 'ASC']));
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

        $this->em->persist($group);
        if (! $this->flush()) {
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
        if (! $this->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withJson($this->group);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/set-public/{flag}",
     *     operationId="setPublic",
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
     *         name="flag",
     *         in="path",
     *         required=true,
     *         description="0 = not public, 1 = public.",
     *         type="integer",
     *         enum={0, 1}
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Visibility changed.",
     *         @SWG\Schema(ref="#/definitions/Group")
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
    public function setPublic(string $id, string $flag): Response
    {
        if (! $this->findGroup($id)) {
            return $this->res->withStatus(404);
        }

        $this->group->setPublic((bool) $flag);
        if (! $this->flush()) {
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

        $this->em->remove($this->group);
        if (! $this->flush()) {
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
     * Retruns true if another group with that name already exists.
     *
     * @param string $name Group name.
     * @param int $id Group ID.
     * @return boolean
     */
    private function otherGroupExists(string $name, int $id = null): bool
    {
        $group = $this->gr->findOneBy(['name' => $name]);

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

        if ($type === 'manager') {
            $hasGroups = $this->player->getManagerGroups();
        } elseif ($type === 'member') {
            $hasGroups = $this->player->getGroups();
        }

        $add = true;
        foreach ($hasGroups as $gp) {
            if ($gp->getId() === $this->group->getId()) {
                $add = false;
                break;
            }
        }

        if ($add && $type === 'manager') {
            $this->group->addManager($this->player);
        } elseif ($add && $type === 'member') {
            $this->player->addGroup($this->group);
        }

        if (! $this->flush()) {
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

        if (! $this->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
    }

    private function findGroup(string $id): bool
    {
        $this->group = $this->gr->find((int) $id);
        if ($this->group === null) {
            return false;
        }

        return true;
    }

    private function findGroupAndPlayer(string $groupId, string $playerId): bool
    {
        $this->group = $this->gr->find((int) $groupId);
        $this->player = $this->pr->find((int) $playerId);

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

    private function flush(): bool
    {
        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return false;
        }

        return true;
    }
}
