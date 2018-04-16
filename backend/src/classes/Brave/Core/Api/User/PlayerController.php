<?php
namespace Brave\Core\Api\User;

use Brave\Core\Entity\PlayerRepository;
use Brave\Core\Entity\RoleRepository;
use Brave\Core\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Response;

/**
 * @SWG\Tag(
 *     name="Player",
 *     description="Player management."
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
     *     path="/user/player/all",
     *     operationId="all",
     *     summary="List all players.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Player"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function all()
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
     *     path="/user/player/group-managers",
     *     operationId="groupManagers",
     *     summary="List all players with the role group-manger.",
     *     description="Needs role: group-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Player"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupManagers()
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
     *     operationId="roles",
     *     summary="List all roles of one player.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
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
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Role"))
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
    public function roles($id)
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
     *     path="/user/player/{id}/add-role/{name}",
     *     operationId="addRole",
     *     summary="Add a role to the player.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="name",
     *         in="path",
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
    public function addRole($id, $name)
    {
        $player = $this->pr->find((int) $id);
        $role = $this->rr->findOneBy(['name' => $name]);

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
     *     path="/user/player/{id}/remove-role/{name}",
     *     operationId="removeRole",
     *     summary="Remove a role from a player.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="name",
     *         in="path",
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
    public function removeRole($id, $name)
    {
        $player = $this->pr->find((int) $id);
        $role = $this->rr->findOneBy(['name' => $name]);

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
