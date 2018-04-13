<?php
namespace Brave\Core\Api\User;

use Brave\Core\Entity\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Response;
use Slim\Http\Request;
use Brave\Core\Entity\Group;

class GroupController
{

    private $res;

    private $log;

    private $gr;

    private $em;

    public function __construct(Response $res, LoggerInterface $log,
        GroupRepository $gr, EntityManagerInterface $em)
    {
        $this->log = $log;
        $this->res = $res;
        $this->gr = $gr;
        $this->em = $em;
    }

    /**
     * @SWG\Get(
     *     path="/user/group/list",
     *     summary="Lists all groups. Needs role: group-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(
     *             type="array",
     *             @SWG\Items(ref="#/definitions/Group")
     *         )
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function list()
    {
        return $this->res->withJson($this->gr->findAll());
    }

    /**
     * @SWG\Post(
     *     path="/user/group/create",
     *     summary="Create a group. Needs role: group-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="name",
     *         in="query",
     *         required=true,
     *         description="Name of the group.",
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The created group.",
     *         @SWG\Schema(ref="#/definitions/Group")
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="If parameter is missing."
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
        if ($name === '') {
            return $this->res->withStatus(400);
        }

        $group = new Group();
        $group->setName($name);

        try {
            $this->em->persist($group);
            $this->em->flush();
        } catch(\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return $this->res->withStatus(500);
        }

        return $this->res->withJson($group);
    }

    /**
     * @SWG\Put(
     *     path="/user/group/rename",
     *     summary="Renames a group. Needs role: group-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="query",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="name",
     *         in="query",
     *         required=true,
     *         description="New name for the group.",
     *         type="string"
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
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function rename(Request $request)
    {
        $id = (int) $request->getParam('id', 0);
        $name = $request->getParam('name', '');
        if ($id === 0 || $name === '') {
            return $this->res->withStatus(400);
        }

        $group = $this->gr->find($id);
        if ($group === null) {
            return $this->res->withStatus(404);
        }

        $group->setName($name);
        try {
            $this->em->flush();
        } catch(\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return $this->res->withStatus(500);
        }

        return $this->res->withJson($group);
    }

    /**
     * @SWG\Delete(
     *     path="/user/group/delete",
     *     summary="Delete a group. Needs role: group-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="query",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Group was deleted."
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="If parameter is missing."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="If the group was not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function delete(Request $request)
    {
        $id = (int) $request->getParam('id', 0);
        if ($id === 0) {
            return $this->res->withStatus(400);
        }

        $group = $this->gr->find($id);
        if ($group === null) {
            return $this->res->withStatus(404);
        }

        try {
            $this->em->remove($group);
            $this->em->flush();
        } catch(\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
    }
}
