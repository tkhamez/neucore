<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Character;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Exception;
use Neucore\Service\Account;
use Neucore\Service\ObjectManager;
use Neucore\Service\ServiceRegistration;
use Neucore\Service\UserAuth;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @OA\Tag(
 *     name="Player",
 *     description="Player management."
 * )
 * @OA\Schema(
 *     schema="CharacterGroup",
 *     required={"player_id", "characters"},
 *     @OA\Property(
 *         property="player_id",
 *         type="integer",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="characters",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Character")
 *     )
 * )
 */
class PlayerController extends BaseController
{
    const COLUMN_PLAYER = 'player';

    const COLUMN_GROUP = 'group';

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var UserAuth
     */
    private $userAuth;

    /**
     * @var array
     */
    private $assignableRoles = [
        Role::APP_ADMIN,
        Role::GROUP_ADMIN,
        Role::SERVICE_ADMIN,
        Role::STATISTICS,
        Role::USER_ADMIN,
        Role::USER_MANAGER,
        Role::USER_CHARS,
        Role::ESI,
        Role::SETTINGS,
        Role::TRACKING_ADMIN,
        Role::WATCHLIST_ADMIN,
    ];

    /**
     * @var array
     */
    private $availableStatus = [
        Player::STATUS_STANDARD,
        Player::STATUS_MANAGED,
    ];

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log,
        UserAuth $userAuth
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->log = $log;
        $this->userAuth = $userAuth;
    }

    /**
     * @OA\Get(
     *     path="/user/player/show",
     *     operationId="show",
     *     summary="Return the logged in player with all properties.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="The player information.",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function show(): ResponseInterface
    {
        return $this->withJson($this->getUser($this->userAuth)->getPlayer());
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/groups-disabled",
     *     operationId="groupsDisabled",
     *     summary="Check whether groups for this account are disabled or will be disabled soon.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="True if groups are disabled, otherwise false.",
     *         @OA\JsonContent(type="boolean")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupsDisabled(Account $accountService): ResponseInterface
    {
        // check state and ignore delay
        if ($accountService->groupsDeactivated($this->getUser($this->userAuth)->getPlayer(), true)) {
            return $this->withJson(true);
        }

        return $this->withJson(false);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/{id}/groups-disabled",
     *     operationId="groupsDisabledById",
     *     summary="Check whether groups for this account are disabled or will be disabled soon.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="True if groups are disabled, otherwise false.",
     *         @OA\JsonContent(type="boolean")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Player not found."
     *     )
     * )
     */
    public function groupsDisabledById(string $id, Account $accountService): ResponseInterface
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);

        if ($player === null) {
            return $this->response->withStatus(404);
        }

        if ($accountService->groupsDeactivated($player, true)) { // true = ignore delay
            return $this->withJson(true);
        }

        return $this->withJson(false);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/player/add-application/{gid}",
     *     operationId="addApplication",
     *     summary="Submit a group application.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="gid",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Application submitted."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Group not found."
     *     )
     * )
     */
    public function addApplication(string $gid): ResponseInterface
    {
        // players can only apply to public groups
        $criteria = ['id' => (int) $gid, 'visibility' => Group::VISIBILITY_PUBLIC];
        $group = $this->repositoryFactory->getGroupRepository()->findOneBy($criteria);
        if ($group === null) {
            return $this->response->withStatus(404);
        }

        $player = $this->getUser($this->userAuth)->getPlayer();

        // update existing or create new application
        $groupApplication = $this->repositoryFactory->getGroupApplicationRepository()->findOneBy([
            self::COLUMN_PLAYER => $player->getId(),
            self::COLUMN_GROUP => $group->getId()
        ]);
        if (! $groupApplication) {
            $groupApplication = new GroupApplication();
            $groupApplication->setPlayer($player);
            $groupApplication->setGroup($group);
            $this->objectManager->persist($groupApplication);
        }
        $groupApplication->setStatus(GroupApplication::STATUS_PENDING);
        $groupApplication->setCreated(new \DateTime());

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/player/remove-application/{gid}",
     *     operationId="removeApplication",
     *     summary="Cancel a group application.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="gid",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Application canceled."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Application not found."
     *     )
     * )
     */
    public function removeApplication(string $gid): ResponseInterface
    {
        $groupApplications = $this->repositoryFactory->getGroupApplicationRepository()->findBy([
            self::COLUMN_PLAYER => $this->getUser($this->userAuth)->getPlayer()->getId(),
            self::COLUMN_GROUP => (int) $gid
        ]);

        if (empty($groupApplications)) {
            return $this->response->withStatus(404);
        }

        foreach ($groupApplications as $groupApplication) { // there should only be one
            $this->objectManager->remove($groupApplication);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/show-applications",
     *     operationId="showApplications",
     *     summary="Show all group applications.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="The group applications.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/GroupApplication"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function showApplications(): ResponseInterface
    {
        $groupApplications = $this->repositoryFactory->getGroupApplicationRepository()->findBy([
            self::COLUMN_PLAYER => $this->getUser($this->userAuth)->getPlayer()->getId()
        ]);

        return $this->withJson($groupApplications);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/player/leave-group/{gid}",
     *     operationId="leaveGroup",
     *     summary="Leave a group.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="gid",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group left."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Group not found."
     *     )
     * )
     */
    public function leaveGroup(string $gid): ResponseInterface
    {
        $group = $this->repositoryFactory->getGroupRepository()->findOneBy(['id' => (int) $gid]);
        if ($group === null) {
            return $this->response->withStatus(404);
        }

        $this->getUser($this->userAuth)->getPlayer()->removeGroup($group);

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Put(
     *     path="/user/player/set-main/{cid}",
     *     operationId="setMain",
     *     summary="Change the main character from the player account.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="Character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The main character.",
     *         @OA\JsonContent(ref="#/components/schemas/Character")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Character not found on this account."
     *     )
     * )
     */
    public function setMain(string $cid): ResponseInterface
    {
        $main = null;
        $player = $this->getUser($this->userAuth)->getPlayer();
        foreach ($player->getCharacters() as $char) {
            if ($char->getId() === (int) $cid) {
                $char->setMain(true);
                $main = $char;
                $player->setName($main->getName());
            } else {
                $char->setMain(false);
            }
        }

        if ($main === null) {
            return $this->response->withStatus(404);
        }

        return $this->flushAndReturn(200, $main);
    }

    /**
     * @OA\Put(
     *     path="/user/player/{id}/set-status/{status}",
     *     operationId="setStatus",
     *     summary="Change the player's account status.",
     *     description="Needs role: user-manager",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="path",
     *         required=true,
     *         description="The new status.",
     *         @OA\Schema(type="string", enum={"standard", "managed"})
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Status changed."
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid player or status."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function setStatus(string $id, string $status, Account $account): ResponseInterface
    {
        $validStatus = [
            Player::STATUS_STANDARD,
            Player::STATUS_MANAGED,
        ];
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);
        if (! in_array($status, $validStatus) || ! $player) {
            return $this->response->withStatus(400);
        }

        if ($status !== $player->getStatus()) {
            // remove all groups and change status
            foreach ($player->getGroups() as $group) {
                $player->removeGroup($group);
            }
            $player->setStatus($status);

            if ($player->getStatus() === Player::STATUS_STANDARD) {
                $account->updateGroups($player->getId());
            }
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/with-characters",
     *     operationId="withCharacters",
     *     summary="List all players with characters.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function withCharacters(): ResponseInterface
    {
        return $this->playerList($this->repositoryFactory->getPlayerRepository()->findWithCharacters());
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/invalid-token",
     *     operationId="invalidToken",
     *     summary="List all players with a character with an invalid token.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function invalidToken(): ResponseInterface
    {
        return $this->playerList($this->repositoryFactory->getPlayerRepository()->findWithInvalidToken());
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/no-token",
     *     operationId="noToken",
     *     summary="List all players with a character with no token.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function noToken(): ResponseInterface
    {
        return $this->playerList($this->repositoryFactory->getPlayerRepository()->findWithNoToken());
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/without-characters",
     *     operationId="withoutCharacters",
     *     summary="List all players without characters.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function withoutCharacters(): ResponseInterface
    {
        return $this->playerList($this->repositoryFactory->getPlayerRepository()->findWithoutCharacters());
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/app-managers",
     *     operationId="appManagers",
     *     summary="List all players with the role app-manger.",
     *     description="Needs role: app-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function appManagers(): ResponseInterface
    {
        return $this->getPlayerByRole(Role::APP_MANAGER);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/group-managers",
     *     operationId="groupManagers",
     *     summary="List all players with the role group-manger.",
     *     description="Needs role: group-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupManagers(): ResponseInterface
    {
        return $this->getPlayerByRole(Role::GROUP_MANAGER);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/with-role/{name}",
     *     operationId="withRole",
     *     summary="List all players with a role.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Role name.",
     *         @OA\Schema(
     *             type="string",
     *             enum={"user", "user-admin", "user-manager", "user-chars", "group-admin", "group-manager",
     *                   "service-admin", "app-admin", "app-manager", "esi", "settings", "tracking", "tracking-admin",
     *                   "watchlist", "watchlist-manager", "watchlist-admin"}
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid role name."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function withRole(string $name): ResponseInterface
    {
        if (! in_array($name, [
            Role::USER_ADMIN,
            Role::USER_MANAGER,
            Role::USER_CHARS,
            Role::GROUP_ADMIN,
            Role::SERVICE_ADMIN,
            Role::STATISTICS,
            Role::APP_ADMIN,
            Role::ESI,
            Role::SETTINGS,
            Role::TRACKING_ADMIN,
            Role::WATCHLIST_ADMIN,
            Role::GROUP_MANAGER,
            Role::APP_MANAGER,
            Role::TRACKING,
            Role::WATCHLIST,
            Role::WATCHLIST_MANAGER,
        ])) {
            return $this->response->withStatus(400);
        }

        return $this->getPlayerByRole($name);
    }

    /**
     * @OA\Get(
     *     path="/user/player/with-status/{name}",
     *     operationId="withStatus",
     *     summary="Lists all players with characters who have a certain status.",
     *     description="Needs role: user-admin, user-manager",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Status name.",
     *         @OA\Schema(type="string", enum={"standard", "managed"})
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid status name."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function withStatus(string $name): ResponseInterface
    {
        if (! in_array($name, $this->availableStatus)) {
            return $this->response->withStatus(400);
        }

        return $this->playerList($this->repositoryFactory->getPlayerRepository()->findWithCharactersAndStatus($name));
    }

    /**
     * @OA\Put(
     *     path="/user/player/{id}/add-role/{name}",
     *     operationId="addRole",
     *     summary="Add a role to the player.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the role.",
     *         @OA\Schema(
     *             type="string",
     *             enum={"app-admin", "user-manager", "user-chars", "group-admin", "service-admin", "user-admin",
     *                   "app-admin", "esi", "settings", "tracking-admin", "watchlist-admin"}
     *         )
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
     *         description="Player and/or role not found or invalid."
     *     )
     * )
     */
    public function addRole(string $id, string $name): ResponseInterface
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $name]);

        if (! $player || ! $role || ! in_array($role->getName(), $this->assignableRoles)) {
            return $this->response->withStatus(404);
        }

        if (! $player->hasRole($role->getName())) {
            $player->addRole($role);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/player/{id}/remove-role/{name}",
     *     operationId="removeRole",
     *     summary="Remove a role from a player.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the role.",
     *         @OA\Schema(
     *             type="string",
     *             enum={"app-admin", "user-manager", "user-chars", "group-admin", "service-admin", "user-admin",
     *                   "app-admin", "esi", "settings", "tracking-admin", "watchlist-admin"}
     *         )
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
     *         description="Player and/or role not found or invalid."
     *     )
     * )
     */
    public function removeRole(string $id, string $name): ResponseInterface
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $name]);

        if (! $player || ! $role || ! in_array($role->getName(), $this->assignableRoles)) {
            return $this->response->withStatus(404);
        }

        $player->removeRole($role);

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/player/{id}/show",
     *     operationId="showById",
     *     summary="Show all data from a player.",
     *     description="Needs role: user-admin, user-manager",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The player (this includes the removedCharacters, incomingCharacters
                            and serviceAccounts properties).",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Player not found."
     *     )
     * )
     */
    public function showById(string $id, ServiceRegistration $serviceRegistration): ResponseInterface
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);

        if ($player === null) {
            return $this->response->withStatus(404);
        }

        $json = $player->jsonSerialize(false, true); // with character name changes
        $json['removedCharacters'] = $player->getRemovedCharacters();
        $json['incomingCharacters'] = $player->getIncomingCharacters();
        $json['serviceAccounts'] = $this->getServiceAccounts($player, $serviceRegistration);

        return $this->withJson($json);
    }

    /**
     * @OA\Get(
     *     path="/user/player/{id}/characters",
     *     operationId="characters",
     *     summary="Show player with characters, moved characters, groups and service accounts.",
     *     description="Needs role: app-admin, group-admin, user-manager, user-chars, watchlist, tracking<br>
                        If a user only has the tracking or watchlist roles, the player must have a character in a
                        corporation for which the user has access to the member tracking data or the player must
                        be on a watchlist that the user can view.",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The player.",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Player not found."
     *     )
     * )
     */
    public function characters(
        string $id,
        UserAuth $userAuth,
        ServiceRegistration $serviceRegistration
    ): ResponseInterface {
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);

        if ($player === null) {
            return $this->response->withStatus(404);
        }

        // Check special tracking and watchlist permissions
        if (
            $this->needsTrackingOrWatchlistPermission($userAuth) &&
            ! $this->hasTrackingPermission($userAuth, $player) &&
            ! $this->hasWatchlistPermission($userAuth, $player)
        ) {
            return $this->response->withStatus(403);
        }

        return $this->withJson([
            'id' => $player->getId(),
            'name' => $player->getName(),
            'characters' => array_map(function (Character $character) {
                return $character->jsonSerialize(false, true, true);
            }, $player->getCharacters()),
            'groups' => $player->getGroups(),
            'removedCharacters' => $player->getRemovedCharacters(),
            'incomingCharacters' => $player->getIncomingCharacters(),
            'serviceAccounts' => $this->getServiceAccounts($player, $serviceRegistration),
        ]);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Post(
     *     path="/user/player/group-characters-by-account",
     *     operationId="playerGroupCharactersByAccount",
     *     summary="Accepts a list of character names and returns them grouped by account.",
     *     description="Needs role: user-chars.<br>
                        The returned character list always contains the main character as the first character
                        in the list. Characters that do not exist will all be added to a separate group as the
                        last element of the result list.",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="List of character names, one per line.",
     *         @OA\MediaType(mediaType="text/plain", @OA\Schema(type="string")),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of character groups, only the id and name properties will be included.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/CharacterGroup"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupCharactersByAccount(ServerRequestInterface $request): ResponseInterface
    {
        $result = [];
        $notFound = ['player_id' => null, 'characters' => []];

        $charRepo = $this->repositoryFactory->getCharacterRepository();
        foreach (array_unique(explode("\n", $request->getBody()->__toString())) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $char = $charRepo->findOneBy(['name' => $name]);
            if ($char) {
                $player = $char->getPlayer();
                if (! isset($result[$player->getId()])) {
                    $result[$player->getId()] = ['player_id' => $player->getId(), 'characters' => []];
                    $main = $player->getMain();
                    if ($main) {
                        $result[$player->getId()]['characters'][$main->getId()] = $main->jsonSerialize(true);
                    } else {
                        $result[$player->getId()]['characters'][0] = ['id' => 0, 'name' => '[no main]'];
                    }
                }
                if (! isset($result[$player->getId()]['characters'][$char->getId()])) {
                    $result[$player->getId()]['characters'][$char->getId()] = $char->jsonSerialize(true);
                }
            } else {
                $notFound['characters'][] = ['id' => 0, 'name' => $name];
            }
        }

        // remove IDs from indexes
        $result = array_values($result);
        foreach ($result as $idx => $player) {
            $result[$idx]['characters'] = array_values($player['characters']);
        }

        // add missing chars
        if (! empty($notFound['characters'])) {
            $result[] = $notFound;
        }

        return $this->withJson($result);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Delete(
     *     path="/user/player/delete-character/{id}",
     *     operationId="deleteCharacter",
     *     summary="Delete a character.",
     *     description="Needs role: user, user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the character.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="admin-reason",
     *         in="query",
     *         description="Specifies a reason if a user admin triggered the deletion.
                            ('deleted-by-admin' will not create a 'Removed Character' entry.)",
     *         @OA\Schema(type="string", enum={"deleted-owner-changed", "deleted-manually", "deleted-by-admin"})
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Character was deleted."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized or feature disabled."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Character not found."
     *     ),
     *     @OA\Response(
     *         response="409",
     *         description="Trying to delete logged in character."
     *     )
     * )
     */
    public function deleteCharacter(
        string $id,
        ServerRequestInterface $request,
        Account $characterService
    ): ResponseInterface {
        $reason = $this->getQueryParam($request, 'admin-reason', '');
        $admin = $this->getUser($this->userAuth)->getPlayer()->hasRole(Role::USER_ADMIN) && $reason !== '';

        // check "allow deletion" settings
        if (! $admin) {
            $allowDeletion = $this->repositoryFactory->getSystemVariableRepository()->findOneBy(
                ['name' => SystemVariable::ALLOW_CHARACTER_DELETION]
            );
            if ($allowDeletion && $allowDeletion->getValue() === '0') {
                return $this->response->withStatus(403);
            }
        }

        // check if character to delete is logged in
        if ((int) $id === $this->getUser($this->userAuth)->getId()) {
            return $this->response->withStatus(409);
        }

        // get character to delete
        $char = $this->repositoryFactory->getCharacterRepository()->find((int) $id);
        if ($char === null) {
            return $this->response->withStatus(404);
        }

        // check for a valid reason if an admin deletes the character,
        // otherwise check if character belongs to the logged in player account.
        if ($admin && ! in_array($reason, [
            RemovedCharacter::REASON_DELETED_MANUALLY,
            RemovedCharacter::REASON_DELETED_OWNER_CHANGED,
            RemovedCharacter::REASON_DELETED_BY_ADMIN,
        ])) {
            return $this->response->withStatus(403);
        } elseif (! $admin && $this->getUser($this->userAuth)->getPlayer()->hasCharacter((int) $char->getId())) {
            $reason = RemovedCharacter::REASON_DELETED_MANUALLY;
        } elseif (! $admin) {
            return $this->response->withStatus(403);
        }

        // delete char
        $characterService->deleteCharacter($char, $reason, $this->getUser($this->userAuth)->getPlayer());

        return $this->flushAndReturn(204);
    }

    private function getPlayerByRole(string $roleName): ResponseInterface
    {
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $roleName]);
        if ($role === null) {
            $this->log->critical('PlayerController->getManagers(): role "'.$roleName.'" not found.');
            return $this->withJson([]);
        }

        return $this->playerList($role->getPlayers());
    }

    /**
     * @param Player[] $players
     * @return ResponseInterface
     */
    private function playerList(array $players): ResponseInterface
    {
        $ret = [];
        foreach ($players as $player) {
            $ret[] = $player->jsonSerialize(true);
        }

        return $this->withJson($ret);
    }

    private function needsTrackingOrWatchlistPermission(UserAuth $userAuth): bool
    {
        $roles = $this->getUser($userAuth)->getPlayer()->getRoleNames();
        $neededRolesExceptTracking = array_intersect(
            $roles,
            [Role::APP_ADMIN, Role::GROUP_ADMIN, Role::USER_MANAGER, Role::USER_CHARS]
        );
        if (
            (in_array(Role::TRACKING, $roles) || in_array(Role::WATCHLIST, $roles)) &&
            empty($neededRolesExceptTracking)
        ) {
            return true;
        }
        return false;
    }

    private function hasTrackingPermission(UserAuth $userAuth, Player $player): bool
    {
        $requiredGroups = [];
        foreach ($player->getCharacters() as $character) {
            if ($character->getCorporation() !== null) {
                foreach ($character->getCorporation()->getGroupsTracking() as $group) {
                    $requiredGroups[] = $group->getId();
                }
            }
        }
        $userGroupIds = $this->getUser($userAuth)->getPlayer()->getGroupIds();
        if (empty($userGroupIds) || empty(array_intersect($requiredGroups, $userGroupIds))) {
            return false;
        }
        return true;
    }

    private function hasWatchlistPermission(UserAuth $userAuth, Player $player): bool
    {
        // Collect all watchlists that the user can view
        $watchlistsWithAccess = [];
        $userGroupIds = $this->getUser($userAuth)->getPlayer()->getGroupIds();
        foreach ($this->repositoryFactory->getWatchlistRepository()->findBy([]) as $watchlist) {
            $requiredGroups = [];
            foreach ($watchlist->getGroups() as $group) {
                $requiredGroups[] = $group->getId();
            }
            if (! empty($userGroupIds) && ! empty(array_intersect($requiredGroups, $userGroupIds))) {
                $watchlistsWithAccess[] = $watchlist;
            }
        }

        // collect corporations and alliances from configuration
        $watchlistsCorporationIds = [];
        $watchlistsAllianceIds = [];
        foreach ($watchlistsWithAccess as $watchlistWithAccess) {
            foreach ($watchlistWithAccess->getCorporations() as $corporation) {
                $watchlistsCorporationIds[] = $corporation->getId();
            }
            foreach ($watchlistWithAccess->getAlliances() as $alliance) {
                $watchlistsAllianceIds[] = $alliance->getId();
            }
        }

        // collect corporations and alliances from player
        $playerCorporationIds = [];
        $playerAllianceIds = [];
        foreach ($player->getCharacters() as $character) {
            if ($character->getCorporation() !== null) {
                $playerCorporationIds[] = $character->getCorporation()->getId();
                if ($character->getCorporation()->getAlliance() !== null) {
                    $playerAllianceIds[] = $character->getCorporation()->getAlliance()->getId();
                }
            }
        }

        // allow if player if part of the watchlist
        if (array_intersect($watchlistsAllianceIds, $playerAllianceIds)) {
            return true;
        }
        if (array_intersect($watchlistsCorporationIds, $playerCorporationIds)) {
            return true;
        }

        return false;
    }

    private function getServiceAccounts(Player $player, ServiceRegistration $serviceRegistration): array
    {
        $result = [];

        $services = $this->repositoryFactory->getServiceRepository()->findBy([]);
        foreach ($services as $service) {
            $implementation = $serviceRegistration->getServiceImplementation($service);
            $accounts = [];
            if ($implementation) {
                try {
                    $accounts = $serviceRegistration->getAccounts($implementation, $player->getCharacters());
                } catch (Exception $e) {
                    // do nothing, service needs to log it's errors
                }
            }
            foreach ($accounts as $account) {
                // This is the OpenAPI schema defined in Neucore\Api class
                $result[] = [
                    'serviceId' => $service->getId(),
                    'serviceName' => $service->getName(),
                    'characterId' => $account->getCharacterId(),
                    'username' => $account->getUsername(),
                    'status' => $account->getStatus(),
                ];
            }
        }

        return $result;
    }
}
