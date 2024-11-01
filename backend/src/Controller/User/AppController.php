<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\App;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Service\Account;
use Neucore\Util\Crypto;
use Neucore\Service\UserAuth;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: 'App', description: 'Application management.')]
class AppController extends BaseController
{
    private App $application;

    private Player $player;

    private Group $group;

    private Role $role;

    private EveLogin $eveLogin;

    private array $availableRoles = [
        Role::APP_GROUPS,
        Role::APP_CHARS,
        Role::APP_TRACKING,
        Role::APP_ESI_LOGIN,
        Role::APP_ESI_PROXY,
        Role::APP_ESI_TOKEN,
    ];

    #[OA\Get(
        path: '/user/app/all',
        operationId: 'userAppAll',
        description: 'Needs role: app-admin',
        summary: 'List all apps.',
        security: [['Session' => []]],
        tags: ['App'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of apps (only id and name properties are returned).',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/App')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function all(): ResponseInterface
    {
        $apps = [];
        foreach ($this->repositoryFactory->getAppRepository()->findBy([], ['name' => 'ASC']) as $app) {
            $apps[] = [
                'id' => $app->getId(),
                'name' => $app->getName(),
            ];
        }
        return $this->withJson($apps);
    }

    #[OA\Post(
        path: '/user/app/create',
        operationId: 'create',
        description: 'Needs role: app-admin<br>' .
            'Generates a random secret that must be changed by an app manager.',
        summary: 'Create an app.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            description: 'Name of the app.',
                            type: 'string',
                            maxLength: 255
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        tags: ['App'],
        responses: [
            new OA\Response(
                response: '201',
                description: 'The new app.',
                content: new OA\JsonContent(ref: '#/components/schemas/App')
            ),
            new OA\Response(response: '400', description: 'App name is invalid/missing.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '500', description: 'If creation of app failed.')
        ],
    )]
    public function create(ServerRequestInterface $request, LoggerInterface $log): ResponseInterface
    {
        $name = $this->sanitizePrintable($this->getBodyParam($request, 'name', ''));
        if ($name === '') {
            return $this->response->withStatus(400);
        }

        $appRole = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => Role::APP]);
        if ($appRole === null) {
            $log->critical('AppController->create(): Role "'.Role::APP.'" not found.');
            return $this->response->withStatus(500);
        }

        try {
            $secret = Crypto::hex(64);
        } catch (\Exception) {
            return $this->response->withStatus(500);
        }
        $hash = password_hash($secret, Crypto::PASSWORD_HASH);

        $app = new App();
        $app->setName($name);
        $app->setSecret($hash);
        $app->addRole($appRole);

        $this->objectManager->persist($app);

        return $this->flushAndReturn(201, $app);
    }

    #[OA\Put(
        path: '/user/app/{id}/rename',
        operationId: 'rename',
        description: 'Needs role: app-admin',
        summary: 'Rename an app.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            description: 'New name for the app.',
                            type: 'string',
                            maxLength: 255
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'App was renamed.',
                content: new OA\JsonContent(ref: '#/components/schemas/App')
            ),
            new OA\Response(response: '400', description: 'App name is invalid/missing.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'App not found.')
        ],
    )]
    public function rename(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $app = $this->repositoryFactory->getAppRepository()->find((int)$id);
        if ($app === null) {
            return $this->response->withStatus(404);
        }

        $name = $this->sanitizePrintable($this->getBodyParam($request, 'name', ''));
        if ($name === '') {
            return $this->response->withStatus(400);
        }

        $app->setName($name);

        return $this->flushAndReturn(200, $app);
    }

    #[OA\Delete(
        path: '/user/app/{id}/delete',
        operationId: 'delete',
        description: 'Needs role: app-admin',
        summary: 'Delete an app.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'App was deleted.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'App not found.')
        ],
    )]
    public function delete(string $id): ResponseInterface
    {
        $app = $this->repositoryFactory->getAppRepository()->find((int)$id);
        if ($app === null) {
            return $this->response->withStatus(404);
        }

        $this->objectManager->remove($app);

        return $this->flushAndReturn(204);
    }

    #[OA\Get(
        path: '/user/app/{id}/managers',
        operationId: 'managers',
        description: 'Needs role: app-admin',
        summary: 'List all managers of an app.',
        security: [['Session' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'App ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of players ordered by name. Only id, name and roles properties are returned.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Player')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'App not found.')
        ],
    )]
    public function managers(string $id): ResponseInterface
    {
        $ret = [];

        $app = $this->repositoryFactory->getAppRepository()->find((int)$id);
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

    #[OA\Put(
        path: '/user/app/{id}/add-manager/{pid}',
        operationId: 'addManager',
        description: 'Needs role: app-admin',
        summary: 'Assign a player as manager to an app.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'pid',
                description: 'ID of the player.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Player added as manager.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Player and/or app not found.')
        ],
    )]
    public function addManager(string $id, string $pid, Account $account): ResponseInterface
    {
        if (!$this->findAppAndPlayer($id, $pid)) {
            return $this->response->withStatus(404);
        }

        $isManager = [];
        foreach ($this->application->getManagers() as $mg) {
            $isManager[] = $mg->getId();
        }
        if (!in_array($this->player->getId(), $isManager)) {
            if (!$account->mayHaveRole($this->player, Role::APP_MANAGER)) {
                return $this->response->withStatus(400);
            }
            $this->application->addManager($this->player); // needed to persist
            $this->player->addManagerApp($this->application); // needed for check in syncManagerRole()
            $account->syncManagerRole($this->player, Role::APP_MANAGER);
        }

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/app/{id}/remove-manager/{pid}',
        operationId: 'removeManager',
        description: 'Needs role: app-admin',
        summary: 'Remove a manager (player) from an app.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'pid',
                description: 'ID of the player.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Player removed from managers.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Player and/or app not found.')
        ],
    )]
    public function removeManager(string $id, string $pid, Account $account): ResponseInterface
    {
        if (!$this->findAppAndPlayer($id, $pid)) {
            return $this->response->withStatus(404);
        }

        $this->application->removeManager($this->player); // needed to persist
        $this->player->removeManagerApp($this->application); // needed for check in syncManagerRole()
        $account->syncManagerRole($this->player, Role::APP_MANAGER);

        return $this->flushAndReturn(204);
    }

    #[OA\Get(
        path: '/user/app/{id}/show',
        operationId: 'show',
        description: 'Needs role: app-admin, app-manager<br>' .
            'Managers can only see groups of their own apps.',
        summary: 'Shows app information.',
        security: [['Session' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'App ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The app information',
                content: new OA\JsonContent(ref: '#/components/schemas/App')
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'App not found.')
        ],
    )]
    public function show(string $id, UserAuth $uas): ResponseInterface
    {
        $app = $this->repositoryFactory->getAppRepository()->find((int)$id);
        if ($app === null) {
            return $this->response->withStatus(404);
        }

        // check if logged-in user is manager of this app or has the role app-admin
        $authedPlayer = $this->getUser($uas)->getPlayer();
        if (!$authedPlayer->hasRole(Role::APP_ADMIN) && !$app->isManager($authedPlayer)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($app);
    }

    #[OA\Put(
        path: '/user/app/{id}/add-group/{gid}',
        operationId: 'userAppAddGroup',
        description: 'Needs role: app-admin',
        summary: 'Add a group to an app.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'gid',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group added to app.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group and/or app not found.')
        ],
    )]
    public function addGroup(string $id, string $gid): ResponseInterface
    {
        if (!$this->findAppAndGroup($id, $gid)) {
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

    #[OA\Put(
        path: '/user/app/{id}/remove-group/{gid}',
        operationId: 'userAppRemoveGroup',
        description: 'Needs role: app-admin',
        summary: 'Remove a group from an app.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'gid',
                description: 'ID of the group.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group removed from the app.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Group and/or app not found.')
        ],
    )]
    public function removeGroup(string $id, string $gid): ResponseInterface
    {
        if (!$this->findAppAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        $this->application->removeGroup($this->group);

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/app/{id}/add-role/{name}',
        operationId: 'addRole',
        description: 'Needs role: app-admin',
        summary: 'Add a role to the app.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'name',
                description: 'Name of the role.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['app-groups', 'app-chars', 'app-tracking', 'app-esi-login',
                        'app-esi-proxy', 'app-esi-token']
                )
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Role added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'App and/or role not found or invalid.')
        ],
    )]
    public function addRole(string $id, string $name): ResponseInterface
    {
        if (!$this->findAppAndRole($id, $name)) {
            return $this->response->withStatus(404);
        }

        if (!$this->application->hasRole($this->role->getName())) {
            $this->application->addRole($this->role);
        }

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/app/{id}/remove-role/{name}',
        operationId: 'removeRole',
        description: 'Needs role: app-admin',
        summary: 'Remove a role from an app.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'name',
                description: 'Name of the role.',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['app-groups', 'app-chars', 'app-tracking', 'app-esi-login', 'app-esi-proxy',
                        'app-esi-token']
                )
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Role removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'App and/or role not found or invalid.')
        ],
    )]
    public function removeRole(string $id, string $name): ResponseInterface
    {
        if (!$this->findAppAndRole($id, $name)) {
            return $this->response->withStatus(404);
        }

        $this->application->removeRole($this->role);

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/app/{id}/add-eve-login/{eveLoginId}',
        operationId: 'userAppAddEveLogin',
        description: 'Needs role: app-admin',
        summary: 'Add an EVE login to an app.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'eveLoginId',
                description: 'ID of the EVE login.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'EVE login added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'EVE login and/or app not found.')
        ],
    )]
    public function addEveLogin(string $id, string $eveLoginId): ResponseInterface
    {
        if (!$this->findAppAndEveLogin($id, $eveLoginId)) {
            return $this->response->withStatus(404);
        }

        foreach ($this->application->getEveLogins() as $existingEveLogin) {
            if ($existingEveLogin->getId() === (int)$eveLoginId) {
                return $this->response->withStatus(204);
            }
        }

        $this->application->addEveLogin($this->eveLogin);

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/app/{id}/remove-eve-login/{eveLoginId}',
        operationId: 'userAppRemoveEveLogin',
        description: 'Needs role: app-admin',
        summary: 'Remove an EVE login from an app.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'eveLoginId',
                description: 'ID of the EVE login.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'EVE login removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'EVE login and/or app not found.')
        ],
    )]
    public function removeEveLogin(string $id, string $eveLoginId): ResponseInterface
    {
        if (!$this->findAppAndEveLogin($id, $eveLoginId)) {
            return $this->response->withStatus(404);
        }

        $this->application->removeEveLogin($this->eveLogin);

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/app/{id}/change-secret',
        operationId: 'changeSecret',
        description: 'Needs role: app-manager',
        summary: 'Generates a new application secret. The new secret is returned, it cannot be retrieved afterwards.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['App'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the app.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The new secret.',
                content: new OA\JsonContent(type: 'string')
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'App not found.'),
            new OA\Response(response: '500', description: 'Failed to created new secret.')
        ],
    )]
    public function changeSecret(string $id, UserAuth $uas): ResponseInterface
    {
        $app = $this->repositoryFactory->getAppRepository()->find((int)$id);
        if ($app === null) {
            return $this->response->withStatus(404);
        }

        // check if logged-in user is manager
        if (!$app->isManager($this->getUser($uas)->getPlayer())) {
            return $this->response->withStatus(403);
        }

        try {
            $secret = Crypto::hex(64);
        } catch (\Exception) {
            return $this->response->withStatus(500);
        }
        $hash = password_hash($secret, Crypto::PASSWORD_HASH);

        $app->setSecret($hash);

        return $this->flushAndReturn(200, $secret);
    }

    private function findAppAndPlayer(string $id, string $playerId): bool
    {
        $playerEntity = $this->repositoryFactory->getPlayerRepository()->find((int)$playerId);
        if (!$this->findApp($id) || $playerEntity === null) {
            return false;
        }
        $this->player = $playerEntity;

        return true;
    }

    private function findAppAndGroup(string $appId, string $groupId): bool
    {
        $groupEntity = $this->repositoryFactory->getGroupRepository()->find((int)$groupId);
        if (!$this->findApp($appId) || $groupEntity === null) {
            return false;
        }
        $this->group = $groupEntity;

        return true;
    }

    private function findAppAndRole(string $id, string $name): bool
    {
        $roleEntity = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $name]);
        if (!$this->findApp($id) || !$roleEntity || ! in_array($roleEntity->getName(), $this->availableRoles)) {
            return false;
        }
        $this->role = $roleEntity;

        return true;
    }

    private function findAppAndEveLogin(string $id, string $eveLoginId): bool
    {
        $eveLogin = $this->repositoryFactory->getEveLoginRepository()->find($eveLoginId);
        if (!$this->findApp($id) || !$eveLogin) {
            return false;
        }
        $this->eveLogin = $eveLogin;

        return true;
    }

    private function findApp(string $id): bool
    {
        $applicationEntity = $this->repositoryFactory->getAppRepository()->find((int)$id);
        if (!$applicationEntity) {
            return false;
        }
        $this->application = $applicationEntity;

        return true;
    }
}
