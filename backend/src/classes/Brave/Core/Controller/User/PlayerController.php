<?php declare(strict_types=1);

namespace Brave\Core\Controller\User;

use Brave\Core\Controller\BaseController;
use Brave\Core\Entity\Group;
use Brave\Core\Entity\GroupApplication;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\RemovedCharacter;
use Brave\Core\Entity\Role;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\Account;
use Brave\Core\Service\ObjectManager;
use Brave\Core\Service\UserAuth;
use Psr\Log\LoggerInterface;
use Slim\Http\Response;
use Swagger\Annotations as SWG;

/**
 * @SWG\Tag(
 *     name="Player",
 *     description="Player management."
 * )
 */
class PlayerController extends BaseController
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var UserAuth
     */
    private $userAuthService;

    private $availableRoles = [
        Role::APP_ADMIN,
        Role::APP_MANAGER,
        Role::GROUP_ADMIN,
        Role::GROUP_MANAGER,
        Role::USER_ADMIN,
        Role::ESI,
        Role::SETTINGS,
        Role::TRACKING,
        Role::USER_MANAGER,
    ];

    private $availableStatus = [
        Player::STATUS_STANDARD,
        Player::STATUS_MANAGED,
    ];

    public function __construct(
        Response $response,
        ObjectManager $objectManager,
        LoggerInterface $log,
        RepositoryFactory $repositoryFactory,
        UserAuth $uas
    ) {
        parent::__construct($response, $objectManager);

        $this->log = $log;
        $this->repositoryFactory = $repositoryFactory;
        $this->userAuthService = $uas;
    }

    /**
     * @SWG\Get(
     *     path="/user/player/show",
     *     operationId="show",
     *     summary="Return the logged in player with all properties.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The player information.",
     *         @SWG\Schema(ref="#/definitions/Player")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function show(): Response
    {
        return $this->response->withJson($this->userAuthService->getUser()->getPlayer());
    }

    /**
     * @SWG\Get(
     *     path="/user/player/groups-disabled",
     *     operationId="groupsDisabled",
     *     summary="Check whether groups for this account are disabled or will be disabled soon.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="True if groups are disabled, otherwise false.",
     *         @SWG\Schema(type="boolean")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupsDisabled(Account $accountService): Response
    {
        $player = $this->userAuthService->getUser()->getPlayer();

        if ($accountService->groupsDeactivated($player, true)) { // true = ignore delay
            return $this->response->withJson(true);
        }

        return $this->response->withJson(false);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/{id}/groups-disabled",
     *     operationId="groupsDisabledById",
     *     summary="Check whether groups for this account are disabled or will be disabled soon.",
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
     *     @SWG\Response(
     *         response="200",
     *         description="True if groups are disabled, otherwise false.",
     *         @SWG\Schema(type="boolean")
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Player not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupsDisabledById(string $id, Account $accountService): Response
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);

        if ($player === null) {
            return $this->response->withStatus(404);
        }

        if ($accountService->groupsDeactivated($player, true)) { // true = ignore delay
            return $this->response->withJson(true);
        }

        return $this->response->withJson(false);
    }

    /**
     * @SWG\Put(
     *     path="/user/player/add-application/{gid}",
     *     operationId="addApplication",
     *     summary="Submit a group application.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="gid",
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
    public function addApplication(string $gid): Response
    {
        // players can only apply to public groups
        $criteria = ['id' => (int) $gid, 'visibility' => Group::VISIBILITY_PUBLIC];
        $group = $this->repositoryFactory->getGroupRepository()->findOneBy($criteria);
        if ($group === null) {
            return $this->response->withStatus(404);
        }

        $player = $this->userAuthService->getUser()->getPlayer();

        // find existing applications
        $hasApplied = false;
        $groupApps = $this->repositoryFactory->getGroupApplicationRepository()
            ->findBy(['player' => $player->getId()]);
        foreach ($groupApps as $application) {
            if ($application->getGroup() && $application->getGroup()->getId() === $group->getId()) {
                $hasApplied = true;
                break;
            }
        }

        if (! $hasApplied) {
            $newApplication = new GroupApplication();
            $newApplication->setPlayer($player);
            $newApplication->setGroup($group);
            $newApplication->setCreated(date_create());
            $this->objectManager->persist($newApplication);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @SWG\Put(
     *     path="/user/player/remove-application/{gid}",
     *     operationId="removeApplication",
     *     summary="Cancel a group application.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="gid",
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
     *         description="Application not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function removeApplication(string $gid): Response
    {
        $player = $this->userAuthService->getUser()->getPlayer();

        $groupApplications = $this->repositoryFactory->getGroupApplicationRepository()
            ->findBy(['player' => $player->getId(), 'group' => (int) $gid]);

        if (count($groupApplications) === 0) {
            return $this->response->withStatus(404);
        }

        foreach ($groupApplications as $groupApplication) { // there should only be one
            $this->objectManager->remove($groupApplication);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/show-applications",
     *     operationId="showApplications",
     *     summary="Show all group applications.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The group applications.",
     *         @SWG\Schema(ref="#/definitions/GroupApplication")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function showApplications(): Response
    {
        $player = $this->userAuthService->getUser()->getPlayer();

        $groupApplications = $this->repositoryFactory->getGroupApplicationRepository()
            ->findBy(['player' => $player->getId()]);

        return $this->response->withJson($groupApplications);
    }

    /**
     * @SWG\Put(
     *     path="/user/player/leave-group/{gid}",
     *     operationId="leaveGroup",
     *     summary="Leave a group.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="gid",
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
    public function leaveGroup(string $gid): Response
    {
        $group = $this->repositoryFactory->getGroupRepository()->findOneBy(['id' => (int) $gid]);
        if ($group === null) {
            return $this->response->withStatus(404);
        }

        $this->userAuthService->getUser()->getPlayer()->removeGroup($group);

        return $this->flushAndReturn(204);
    }

    /**
     * @SWG\Put(
     *     path="/user/player/set-main/{cid}",
     *     operationId="setMain",
     *     summary="Change the main character from the player account.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="Character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The main character.",
     *         @SWG\Schema(ref="#/definitions/Character")
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Character not found on this account."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function setMain(string $cid): Response
    {
        $main = null;
        $player = $this->userAuthService->getUser()->getPlayer();
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
     * @SWG\Put(
     *     path="/user/player/{id}/set-status/{status}",
     *     operationId="setStatus",
     *     summary="Change the player's account status.",
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
     *         name="status",
     *         in="path",
     *         required=true,
     *         description="The new status.",
     *         type="string",
     *         enum={"standard", "managed"}
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Status changed."
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid player or status."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function setStatus(string $id, string $status): Response
    {
        $authPlayer = $this->userAuthService->getUser()->getPlayer();
        if (! $authPlayer->hasRole(Role::USER_ADMIN)) {
            return $this->response->withStatus(403);
        }

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
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/with-characters",
     *     operationId="withCharacters",
     *     summary="List all players with characters.",
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
    public function withCharacters(): Response
    {
        return $this->playerList($this->repositoryFactory->getPlayerRepository()->findWithCharacters());
    }

    /**
     * @SWG\Get(
     *     path="/user/player/without-characters",
     *     operationId="withoutCharacters",
     *     summary="List all players without characters.",
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
    public function withoutCharacters(): Response
    {
        return $this->playerList($this->repositoryFactory->getPlayerRepository()->findWithoutCharacters());
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
        return $this->getPlayerByRole(Role::APP_MANAGER);
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
        return $this->getPlayerByRole(Role::GROUP_MANAGER);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/with-role/{name}",
     *     operationId="withRole",
     *     summary="List all players with a role.",
     *     description="Needs role: user-admin",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Role name.",
     *         type="string",
     *         enum={"app-admin", "app-manager", "group-admin", "group-manager", "user-admin", "user-manager", "esi", "settings", "tracking"}
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Player"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid role name."
     *     ),
     * )
     */
    public function withRole(string $name): Response
    {
        if (! in_array($name, $this->availableRoles)) {
            return $this->response->withStatus(400);
        }

        return $this->getPlayerByRole($name);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/with-status/{name}",
     *     operationId="withStatus",
     *     summary="Lists all players with characters who have a certain status.",
     *     description="Needs role: user-admin, user-manager",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Status name.",
     *         type="string",
     *         enum={"standard", "managed"}
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Player"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid status name."
     *     ),
     * )
     */
    public function withStatus(string $name): Response
    {
        if (! in_array($name, $this->availableStatus)) {
            return $this->response->withStatus(400);
        }

        return $this->playerList($this->repositoryFactory->getPlayerRepository()->findWithCharactersAndStatus($name));
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
     *         enum={"app-admin", "app-manager", "group-admin", "group-manager", "user-admin", "user-manager", "esi", "settings", "tracking"}
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
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $name]);

        if (! $player || ! $role || ! in_array($role->getName(), $this->availableRoles)) {
            return $this->response->withStatus(404);
        }

        if (! $player->hasRole($role->getName())) {
            $player->addRole($role);
        }

        return $this->flushAndReturn(204);
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
     *         enum={"app-admin", "app-manager", "group-admin", "group-manager", "user-admin", "user-manager", "esi", "settings", "tracking"}
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
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $name]);

        if (! $player || ! $role || ! in_array($role->getName(), $this->availableRoles)) {
            return $this->response->withStatus(404);
        }

        $player->removeRole($role);

        return $this->flushAndReturn(204);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/{id}/show",
     *     operationId="showById",
     *     summary="Show all data from a player.",
     *     description="Needs role: user-admin, user-manager",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The player information (this one includes the removedCharacters property).",
     *         @SWG\Schema(ref="#/definitions/Player")
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Player not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function showById(string $id): Response
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);

        if ($player === null) {
            return $this->response->withStatus(404);
        }

        $json = $player->jsonSerialize();
        $json['removedCharacters'] = $player->getRemovedCharacters();

        return $this->response->withJson($json);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/{id}/characters",
     *     operationId="characters",
     *     summary="Show player with characters.",
     *     description="Needs role: app-admin, group-admin, user-manager, group-manager, tracking",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the player.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The player with id, name and characters properties only.",
     *         @SWG\Schema(ref="#/definitions/Player")
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Player not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function characters(string $id): Response
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $id);

        if ($player === null) {
            return $this->response->withStatus(404);
        }

        return $this->response->withJson([
            'id' => $player->getId(),
            'name' => $player->getName(),
            'characters' => $player->getCharacters(),
        ]);
    }

    /**
     * @SWG\Delete(
     *     path="/user/player/delete-character/{id}",
     *     operationId="deleteCharacter",
     *     summary="Delete a character.",
     *     description="Needs role: user",
     *     tags={"Player"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the character.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Character was deleted."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Character not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized or feature disabled."
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="Trying to delete logged in character."
     *     )
     * )
     */
    public function deleteCharacter(string $id, Account $characterService): Response
    {
        // check "allow deletion" settings
        $allowDeletion = $this->repositoryFactory->getSystemVariableRepository()->findOneBy(
            ['name' => SystemVariable::ALLOW_CHARACTER_DELETION]
        );
        if ($allowDeletion && $allowDeletion->getValue() === '0') {
            return $this->response->withStatus(403);
        }

        // check if character to delete is logged in
        $user = $this->userAuthService->getUser();
        if ((int) $id === $user->getId()) {
            return $this->response->withStatus(409);
        }

        // get character to delete
        $char = $this->repositoryFactory->getCharacterRepository()->find((int) $id);
        if ($char === null) {
            return $this->response->withStatus(404);
        }

        // check if character belongs to the logged in player account
        if (! $user->getPlayer()->hasCharacter($char->getId())) {
            return $this->response->withStatus(403);
        }

        // delete char
        $characterService->deleteCharacter($char, RemovedCharacter::REASON_DELETED_MANUALLY);

        return $this->flushAndReturn(204);
    }

    private function getPlayerByRole(string $roleName): Response
    {
        $role = $this->repositoryFactory->getRoleRepository()->findOneBy(['name' => $roleName]);
        if ($role === null) {
            $this->log->critical('PlayerController->getManagers(): role "'.$roleName.'" not found.');
            return $this->response->withJson([]);
        }

        return $this->playerList($role->getPlayers());
    }

    /**
     * @param Player[] $players
     * @return Response
     */
    private function playerList(array $players): Response
    {
        $ret = [];
        foreach ($players as $player) {
            $ret[] = $player->jsonSerialize(true);
        }

        return $this->response->withJson($ret);
    }
}
