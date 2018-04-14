<?php
namespace Brave\Core\Api\User;

use Brave\Core\Entity\PlayerRepository;
use Brave\Core\Entity\RoleRepository;
use Brave\Core\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 *
 * @SWG\Definition(
 *     definition="PlayerList",
 *     type="array",
 *     @SWG\Items(
 *         type="object",
 *         required={"id", "name"},
 *         @SWG\Property(property="id", type="integer"),
 *         @SWG\Property(property="name", type="string")
 *     )
 * )
 */
class PlayerController
{

    private $res;

    private $log;

    private $pr;

    private $rr;

    private $em;

    private $availableRoles = [
        Roles::APP_ADMIN,
        Roles::APP_MANAGER,
        Roles::GROUP_ADMIN,
        Roles::GROUP_MANAGER,
        Roles::USER_ADMIN
    ];

    public function __construct(Response $response, LoggerInterface $log,
        PlayerRepository $pr, RoleRepository $rr, EntityManagerInterface $em)
    {
        $this->res = $response;
        $this->log = $log;
        $this->pr = $pr;
        $this->rr = $rr;
        $this->em = $em;
    }

    /**
     * @SWG\Get(
     *     path="/user/player/list",
     *     summary="Lists all players. Needs role: user-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of players, ordered by name.",
     *         @SWG\Schema(ref="#/definitions/PlayerList")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function list()
    {
        $ret = [];

        foreach ($this->pr->findBy([], ['name' => 'ASC']) as $player) {
            $ret[] = [
                'id' => $player->getId(),
                'name' => $player->getName()
            ];
        }

        return $this->res->withJson($ret);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/list-group-manager",
     *     summary="Lists all players with the role group-manger. Needs role: group-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of players with role group-manger, ordered by name.",
     *         @SWG\Schema(ref="#/definitions/PlayerList")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function listGroupManager()
    {
        $ret = [];

        $role = $this->rr->findOneBy(['name' => Roles::GROUP_MANAGER]);
        if ($role === null) {
            $this->log->critical('PlayerController->listGroupManager(): Role group-manager not found in.');
            return $this->res->withJson($ret);
        }

        foreach ($role->getPlayers() as $player) {
            $ret[] = [
                'id' => $player->getId(),
                'name' => $player->getName()
            ];
        }

        return $this->res->withJson($ret);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/{id}/roles",
     *     summary="List all roles of one player. Needs role: user-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of player.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of roles.",
     *         @SWG\Schema(ref="#/definitions/RoleList")
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="If player was not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function listRoles($id)
    {
        $player = $this->pr->find((int) $id);

        if ($player) {
            return $this->res->withJson($player->getRoles());
        } else {
            return $this->res->withStatus(404);
        }
    }

    /**
     * @SWG\Put(
     *     path="/user/player/{id}/add-role",
     *     summary="Adds a role to the player. Needs role: user-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="role",
     *         in="formData",
     *         required=true,
     *         description="Name of the role.",
     *         type="string",
     *         enum={"app-admin","app-manager","group-admin","group-manager", "user-admin"}
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Role added."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Player and/or role not found or invalid."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function addRole($id, Request $request)
    {
        $player = $this->pr->find((int) $id);

        $role = $this->rr->findOneBy(['name' => $request->getParam('role', '')]);

        if (! $player || ! $role || ! in_array($role->getName(), $this->availableRoles)) {
            return $this->res->withStatus(404);
        }

        if (! $player->hasRole($role->getName())) {
            $player->addRole($role);
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
     *     path="/user/player/{id}/remove-role",
     *     summary="Removes a role from a player. Needs role: user-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="role",
     *         in="formData",
     *         required=true,
     *         description="Name of the role.",
     *         type="string",
     *         enum={"app-admin","app-manager","group-admin","group-manager", "user-admin"}
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Role removed."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Player and/or role not found or invalid."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function removeRole($id, Request $request)
    {
        $player = $this->pr->find((int) $id);
        $role = $this->rr->findOneBy(['name' => $request->getParam('role', '')]);

        if (! $player || ! $role || ! in_array($role->getName(), $this->availableRoles)) {
            return $this->res->withStatus(404);
        }

        $player->removeRole($role);

        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
    }
}
