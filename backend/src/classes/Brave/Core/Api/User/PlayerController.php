<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Entity\GroupRepository;
use Brave\Core\Entity\PlayerRepository;
use Brave\Core\Entity\RoleRepository;
use Brave\Core\Service\UserAuthService;
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
    /**
     * @var Response
     */
    private $res;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var PlayerRepository
     */
    private $pr;

    /**
     * @var RoleRepository
     */
    private $rr;

    /**
     * @var GroupRepository
     */
    private $gr;

    /**
     * @var UserAuthService
     */
    private $uas;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    private $availableRoles = [
        Roles::APP_ADMIN,
        Roles::APP_MANAGER,
        Roles::GROUP_ADMIN,
        Roles::GROUP_MANAGER,
        Roles::USER_ADMIN
    ];

    public function __construct(Response $response, LoggerInterface $log,
        PlayerRepository $pr, RoleRepository $rr, GroupRepository $gr,
        UserAuthService $uas, EntityManagerInterface $em)
    {
        $this->res = $response;
        $this->log = $log;
        $this->pr = $pr;
        $this->rr = $rr;
        $this->gr = $gr;
        $this->uas = $uas;
        $this->em = $em;
    }

    /**
     * @SWG\Get(
     *     path="/user/player/all",
     *     operationId="all",
     *     summary="List all players.",
     *     description="Needs role: user-admin or group-admin",
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
    public function all(): Response
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
     * @SWG\Put(
     *     path="/user/player/add-application/{group}",
     *     operationId="applyGroup",
     *     summary="Submit a group application.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="group",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Application submitted."
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
    public function addApplication(string $group): Response
    {
        return $this->addOrRemoveGroupToFrom('add', 'Application', $group);
    }

    /**
     * @SWG\Put(
     *     path="/user/player/remove-application/{group}",
     *     operationId="cancelApplication",
     *     summary="Cancel a group application.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="group",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Application canceled."
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
    public function removeApplication(string $group): Response
    {
        return $this->addOrRemoveGroupToFrom('remove', 'Application', $group);
    }

    /**
     * @SWG\Put(
     *     path="/user/player/leave-group/{group}",
     *     operationId="leaveGroup",
     *     summary="Leave a group.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="group",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Group left."
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
    public function leaveGroup(string $group): Response
    {
        return $this->addOrRemoveGroupToFrom('remove', 'Group', $group);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/app-managers",
     *     operationId="appManagers",
     *     summary="List all players with the role app-manger.",
     *     description="Needs role: app-admin",
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
    public function appManagers(): Response
    {
        $ret = $this->getManagers(Roles::APP_MANAGER);

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
    public function groupManagers(): Response
    {
        $ret = $this->getManagers(Roles::GROUP_MANAGER);

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
    public function roles(string $id): Response
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
    public function addRole(string $id, string $name): Response
    {
        $player = $this->pr->find((int) $id);
        $role = $this->rr->findOneBy(['name' => $name]);

        if (! $player || ! $role || ! in_array($role->getName(), $this->availableRoles)) {
            return $this->res->withStatus(404);
        }

        if (! $player->hasRole($role->getName())) {
            $player->addRole($role);
        }

        if (! $this->flush()) {
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
    public function removeRole(string $id, string $name): Response
    {
        $player = $this->pr->find((int) $id);
        $role = $this->rr->findOneBy(['name' => $name]);

        if (! $player || ! $role || ! in_array($role->getName(), $this->availableRoles)) {
            return $this->res->withStatus(404);
        }

        $player->removeRole($role);

        if (! $this->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
    }

    private function getManagers(string $roleName): array
    {
        $ret = [];

        $role = $this->rr->findOneBy(['name' => $roleName]);
        if ($role === null) {
            $this->log->critical('PlayerController->getManagers(): role "'.$roleName.'" not found.');
            return $ret;
        }

        foreach ($role->getPlayers() as $player) {
            $ret[] = [
                'id' => $player->getId(),
                'name' => $player->getName()
            ];
        }

        return $ret;
    }

    private function addOrRemoveGroupToFrom(string $action, string $entity, string $groupId): Response
    {
        if ($action === 'add' && $entity === 'Application') {
            // players can only apply to public groups
            $criteria = ['id' => (int) $groupId, 'public' => true];
        } else {
            $criteria = ['id' => (int) $groupId];
        }
        $group = $this->gr->findOneBy($criteria);
        if ($group === null) {
            return $this->res->withStatus(404);
        }

        $player = $this->uas->getUser()->getPlayer();

        if ($action === 'add' && $entity === 'Application') {
            $hasApplied = false;
            foreach ($player->getApplications() as $applic) {
                if ($group->getId() === $applic->getId()) {
                    $hasApplied = true;
                    break;
                }
            }
            if (! $hasApplied) {
                $player->addApplication($group);
            }
        } elseif ($action === 'remove' && $entity === 'Application') {
            $player->removeApplication($group);
        } elseif ($action === 'remove' && $entity === 'Group') {
            $player->removeGroup($group);
        }

        if (! $this->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
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
