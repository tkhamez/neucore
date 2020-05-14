<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\App;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Service\Random;
use Neucore\Service\UserAuth;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @OA\Tag(
 *     name="App",
 *     description="Application management."
 * )
 */
class AppController extends BaseController
{
    /**
     * @var App
     */
    private $application;

    /**
     * @var Player
     */
    private $player;

    /**
     * @var Group
     */
    private $group;

    /**
     * @var Role
     */
    private $role;

    /**
     * @var array
     */
    private $availableRoles = [
        Role::APP_GROUPS,
        Role::APP_CHARS,
        Role::APP_TRACKING,
        Role::APP_ESI,
    ];

    /**
     * @OA\Get(
     *     path="/user/app/all",
     *     operationId="all",
     *     summary="List all apps.",
     *     description="Needs role: app-admin",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of apps (only id and name properties are returned).",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/App"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function all(): ResponseInterface
    {
        $apps = [];
        foreach ($this->repositoryFactory->getAppRepository()->findBy([]) as $app) {
            $apps[] = [
                'id' => $app->getId(),
                'name' => $app->getName(),
            ];
        }
        return $this->withJson($apps);
    }

    /**
     * @OA\Post(
     *     path="/user/app/create",
     *     operationId="create",
     *     summary="Create an app.",
     *     description="Needs role: app-admin<br>Generates a random secret that must be changed by an app manager.",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="name",
     *                     description="Name of the app.",
     *                     type="string",
     *                     maxLength=255
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="The new app.",
     *         @OA\JsonContent(ref="#/components/schemas/App")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="App name is invalid/missing."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="If creation of app failed."
     *     )
     * )
     */
    public function create(ServerRequestInterface $request, LoggerInterface $log): ResponseInterface
    {
        $name = $this->sanitize($this->getBodyParam($request, 'name', ''));
        if ($name === '') {
            return $this->response->withStatus(400);
        }

        $appRole = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => Role::APP]);
        if ($appRole === null) {
            $log->critical('AppController->create(): Role "'.Role::APP.'" not found.');
            return $this->response->withStatus(500);
        }

        try {
            $secret = Random::hex(64);
        } catch (\Exception $e) {
            return $this->response->withStatus(500);
        }
        $hash = password_hash($secret, PASSWORD_BCRYPT);
        if ($hash === false) {
            return $this->response->withStatus(500);
        }

        $app = new App();
        $app->setName($name);
        $app->setSecret($hash);
        $app->addRole($appRole);

        $this->objectManager->persist($app);

        return $this->flushAndReturn(201, $app);
    }

    /**
     * @OA\Put(
     *     path="/user/app/{id}/rename",
     *     operationId="rename",
     *     summary="Rename an app.",
     *     description="Needs role: app-admin",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the app.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="name",
     *                     description="New name for the app.",
     *                     type="string",
     *                     maxLength=255
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="App was renamed.",
     *         @OA\JsonContent(ref="#/components/schemas/App")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="App name is invalid/missing."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="App not found."
     *     )
     * )
     */
    public function rename(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $app = $this->repositoryFactory->getAppRepository()->find((int) $id);
        if ($app === null) {
            return $this->response->withStatus(404);
        }

        $name = $this->sanitize($this->getBodyParam($request, 'name', ''));
        if ($name === '') {
            return $this->response->withStatus(400);
        }

        $app->setName($name);

        return $this->flushAndReturn(200, $app);
    }

    /**
     * @OA\Delete(
     *     path="/user/app/{id}/delete",
     *     operationId="delete",
     *     summary="Delete an app.",
     *     description="Needs role: app-admin",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the app.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="App was deleted."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="App not found."
     *     )
     * )
     */
    public function delete(string $id): ResponseInterface
    {
        $app = $this->repositoryFactory->getAppRepository()->find((int) $id);
        if ($app === null) {
            return $this->response->withStatus(404);
        }

        $this->objectManager->remove($app);

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Get(
     *     path="/user/app/{id}/managers",
     *     operationId="managers",
     *     summary="List all managers of an app.",
     *     description="Needs role: app-admin",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="App ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id, name and roles properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="App not found."
     *     )
     * )
     */
    public function managers(string $id): ResponseInterface
    {
        $ret = [];

        $app = $this->repositoryFactory->getAppRepository()->find((int) $id);
        if ($app === null) {
            return $this->response->withStatus(404);
        }

        foreach ($app->getManagers() as $player) {
            $ret[] = [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'roles' => $player->getRoles(),
            ];
        }

        return $this->withJson($ret);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/app/{id}/add-manager/{pid}",
     *     operationId="addManager",
     *     summary="Assign a player as manager to an app.",
     *     description="Needs role: app-admin",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the app.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="pid",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Player added as manager."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Player and/or app not found."
     *     )
     * )
     */
    public function addManager(string $id, string $pid): ResponseInterface
    {
        if (! $this->findAppAndPlayer($id, $pid)) {
            return $this->response->withStatus(404);
        }

        $isManager = [];
        foreach ($this->application->getManagers() as $mg) {
            $isManager[] = $mg->getId();
        }
        if (! in_array($this->player->getId(), $isManager)) {
            $this->application->addManager($this->player);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/app/{id}/remove-manager/{pid}",
     *     operationId="removeManager",
     *     summary="Remove a manager (player) from an app.",
     *     description="Needs role: app-admin",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the app.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="pid",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Player removed from managers."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Player and/or app not found."
     *     )
     * )
     */
    public function removeManager(string $id, string $pid): ResponseInterface
    {
        if (! $this->findAppAndPlayer($id, $pid)) {
            return $this->response->withStatus(404);
        }

        $this->application->removeManager($this->player);

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Get(
     *     path="/user/app/{id}/show",
     *     operationId="show",
     *     summary="Shows app information.",
     *     description="Needs role: app-admin, app-manager
     *                  Managers can only see groups of their own apps.",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="App ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The app information",
     *         @OA\JsonContent(ref="#/components/schemas/App")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="App not found."
     *     )
     * )
     */
    public function show(string $id, UserAuth $uas): ResponseInterface
    {
        $app = $this->repositoryFactory->getAppRepository()->find((int) $id);
        if ($app === null) {
            return $this->response->withStatus(404);
        }

        // check if logged in user is manager of this app or has the role app-admin
        $authedPlayer = $this->getUser($uas)->getPlayer();
        if (! $authedPlayer->hasRole(Role::APP_ADMIN) && ! $app->isManager($authedPlayer)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($app);
    }

    /**
     * @OA\Put(
     *     path="/user/app/{id}/add-group/{gid}",
     *     operationId="addGroup",
     *     summary="Add a group to an app.",
     *     description="Needs role: app-admin",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the app.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="gid",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group added to app."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Group and/or app not found."
     *     )
     * )
     */
    public function addGroup(string $id, string $gid): ResponseInterface
    {
        if (! $this->findAppAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        $hasGroups = [];
        foreach ($this->application->getGroups() as $gp) {
            $hasGroups[] = $gp->getId();
        }
        if (! in_array($this->group->getId(), $hasGroups)) {
            $this->application->addGroup($this->group);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Put(
     *     path="/user/app/{id}/remove-group/{gid}",
     *     operationId="removeGroup",
     *     summary="Remove a group from an app.",
     *     description="Needs role: app-admin",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the app.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="gid",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group removed from the app."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Group and/or app not found."
     *     )
     * )
     */
    public function removeGroup(string $id, string $gid): ResponseInterface
    {
        if (! $this->findAppAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        $this->application->removeGroup($this->group);

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Put(
     *     path="/user/app/{id}/add-role/{name}",
     *     operationId="addRole",
     *     summary="Add a role to the app.",
     *     description="Needs role: app-admin",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the app.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the role.",
     *         @OA\Schema(type="string", enum={"app-groups", "app-chars", "app-tracking", "app-esi"})
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Role added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="App and/or role not found or invalid."
     *     )
     * )
     */
    public function addRole(string $id, string $name): ResponseInterface
    {
        if (! $this->findAppAndRole($id, $name)) {
            return $this->response->withStatus(404);
        }

        if (! $this->application->hasRole($this->role->getName())) {
            $this->application->addRole($this->role);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/app/{id}/remove-role/{name}",
     *     operationId="removeRole",
     *     summary="Remove a role from an app.",
     *     description="Needs role: app-admin",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the app.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the role.",
     *         @OA\Schema(type="string", enum={"app-groups", "app-chars", "app-tracking", "app-esi"})
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Role removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="App and/or role not found or invalid."
     *     )
     * )
     */
    public function removeRole(string $id, string $name): ResponseInterface
    {
        if (! $this->findAppAndRole($id, $name)) {
            return $this->response->withStatus(404);
        }

        $this->application->removeRole($this->role);

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/app/{id}/change-secret",
     *     operationId="changeSecret",
     *     summary="Generates a new application secret. The new secret is returned, it cannot be retrieved afterwards.",
     *     description="Needs role: app-manager",
     *     tags={"App"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the app.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The new secret.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="App not found."
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Failed to created new secret."
     *     )
     * )
     */
    public function changeSecret(string $id, UserAuth $uas): ResponseInterface
    {
        $app = $this->repositoryFactory->getAppRepository()->find((int) $id);
        if ($app === null) {
            return $this->response->withStatus(404);
        }

        // check if logged in user is manager
        if (! $app->isManager($this->getUser($uas)->getPlayer())) {
            return $this->response->withStatus(403);
        }

        try {
            $secret = Random::hex(64);
        } catch (\Exception $e) {
            return $this->response->withStatus(500);
        }
        $hash = password_hash($secret, PASSWORD_BCRYPT);
        if ($hash === false) {
            return $this->response->withStatus(500);
        }

        $app->setSecret($hash);

        return $this->flushAndReturn(200, $secret);
    }

    private function findAppAndPlayer(string $id, string $playerId): bool
    {
        $playerEntity = $this->repositoryFactory->getPlayerRepository()->find((int) $playerId);
        if (! $this->findApp($id) || $playerEntity === null) {
            return false;
        }
        $this->player = $playerEntity;

        return true;
    }

    private function findAppAndGroup(string $appId, string $groupId): bool
    {
        $groupEntity = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);
        if (! $this->findApp($appId) || $groupEntity === null) {
            return false;
        }
        $this->group = $groupEntity;

        return true;
    }

    private function findAppAndRole(string $id, string $name): bool
    {
        $roleEntity = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $name]);
        if (! $this->findApp($id) || ! $roleEntity || ! in_array($roleEntity->getName(), $this->availableRoles)) {
            return false;
        }
        $this->role = $roleEntity;

        return true;
    }

    private function findApp(string $id): bool
    {
        $applicationEntity = $this->repositoryFactory->getAppRepository()->find((int) $id);
        if (! $applicationEntity) {
            return false;
        }
        $this->application = $applicationEntity;

        return true;
    }

    private function sanitize(string $name): string
    {
        return str_replace(["\r", "\n"], ' ', trim($name));
    }
}
