<?php
namespace Brave\Core\Api\User;

use Brave\Core\Entity\Group;
use Brave\Core\Entity\GroupRepository;
use Brave\Core\Entity\PlayerRepository;
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
    private $res;

    private $log;

    private $gr;

    private $pr;

    private $em;

    private $namePattern = "/^[-._a-zA-Z0-9]+$/";

    public function __construct(Response $res, LoggerInterface $log,
        GroupRepository $gr, PlayerRepository $pr, EntityManagerInterface $em)
    {
        $this->log = $log;
        $this->res = $res;
        $this->gr = $gr;
        $this->pr = $pr;
        $this->em = $em;
    }

    /**
     * @SWG\Get(
     *     path="/user/group/list-all",
     *     operationId="listAll",
     *     summary="Lists all groups. Needs role: group-admin",
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
    public function listAll()
    {
        return $this->res->withJson($this->gr->findAll());
    }

    /**
     * @SWG\Post(
     *     path="/user/group/create",
     *     operationId="create",
     *     summary="Create a group. Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
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
    public function create(Request $request)
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

        try {
            $this->em->persist($group);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return $this->res->withStatus(500);
        }

        return $this->res->withJson($group);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/rename",
     *     operationId="rename",
     *     summary="Renames a group. Needs role: group-admin",
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
    public function rename($id, Request $request)
    {
        $group = $this->gr->find($id);
        if ($group === null) {
            return $this->res->withStatus(404);
        }

        $name = $request->getParam('name', '');
        if (! preg_match($this->namePattern, $name)) {
            return $this->res->withStatus(400);
        }

        if ($this->otherGroupExists($name, $group->getId())) {
            return $this->res->withStatus(409);
        }

        $group->setName($name);
        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return $this->res->withStatus(500);
        }

        return $this->res->withJson($group);
    }

    /**
     * @SWG\Delete(
     *     path="/user/group/{id}/delete",
     *     operationId="delete",
     *     summary="Delete a group. Needs role: group-admin",
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
    public function delete($id)
    {
        $group = $this->gr->find($id);
        if ($group === null) {
            return $this->res->withStatus(404);
        }

        try {
            $this->em->remove($group);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
    }

    /**
     * @SWG\Get(
     *     path="/user/group/{id}/manager",
     *     operationId="manager",
     *     summary="List all managers of a group. Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="group ID.",
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
    public function manager($id)
    {
        $ret = [];

        $group = $this->gr->find($id);
        if ($group === null) {
            return $this->res->withStatus(404);
        }

        foreach ($group->getManagers() as $player) {
            $ret[] = [
                'id' => $player->getId(),
                'name' => $player->getName()
            ];
        }

        return $this->res->withJson($ret);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/add-manager",
     *     operationId="addManager",
     *     summary="Assigns a player as manager to a group. Needs role: group-admin",
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
     *         name="player",
     *         in="formData",
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
    public function addManager($id, Request $request)
    {
        $group = $this->gr->find((int) $id);
        $player = $this->pr->find((int) $request->getParam('player', 0));

        if ($group === null || $player === null) {
            return $this->res->withStatus(404);
        }

        $isManager = [];
        foreach ($player->getManagerGroups() as $mg) {
            $isManager[] = $mg->getId();
        }
        if (! in_array($group->getId(), $isManager)) {
            $group->addManager($player);
        }

        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/{id}/remove-manager",
     *     operationId="removeManager",
     *     summary="Removes a manager (player) from a group. Needs role: group-admin",
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
     *         name="player",
     *         in="formData",
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
    public function removeManager($id, Request $request)
    {
        $group = $this->gr->find((int) $id);
        $player = $this->pr->find((int) $request->getParam('player', 0));

        if ($group === null || $player === null) {
            return $this->res->withStatus(404);
        }

        $group->removeManager($player);

        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
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
}
