<?php
namespace Brave\Core\Api\User;

use Brave\Core\Entity\PlayerRepository;
use Brave\Core\Entity\RoleRepository;
use Brave\Core\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Response;
use Slim\Http\Request;

/**
 *
 */
class RoleController
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

    public function __construct(Response $res, LoggerInterface $log,
        PlayerRepository $pr, RoleRepository $rr, EntityManagerInterface $em)
    {
        $this->log = $log;
        $this->res = $res;
        $this->pr = $pr;
        $this->rr = $rr;
        $this->em = $em;
    }

    /**
     * @SWG\Put(
     *     path="/user/role/add-player",
     *     summary="Adds a role to a player. Needs role: user-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="player",
     *         in="query",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="role",
     *         in="query",
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
    public function addRoleToPlayer(Request $request)
    {
        $player = $this->pr->find($request->getParam('player', 0));
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
     *     path="/user/role/remove-player",
     *     summary="Removes a role from a player. Needs role: user-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="player",
     *         in="query",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="role",
     *         in="query",
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
    public function removeRoleFromPlayer(Request $request)
    {
        $player = $this->pr->find($request->getParam('player', 0));
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
