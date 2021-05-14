<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Account;
use Neucore\Service\ObjectManager;
use Neucore\Service\UserAuth;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="Group",
 *     description="Group management."
 * )
 */
class GroupController extends BaseController
{
    private const ERROR_GROUP_NOT_FOUND = 'Group not found.';

    private const TYPE_MANAGERS = 'managers';

    private const TYPE_MEMBERS = 'members';

    /**
     * @var UserAuth
     */
    private $userAuth;

    /**
     * @var Account
     */
    private $account;

    /**
     * @var string
     */
    private $namePattern = "/^[-._a-zA-Z0-9]+$/";

    /**
     * @var Group
     */
    private $group;

    /**
     * @var Player
     */
    private $player;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        UserAuth $userAuth,
        Account $account
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->userAuth = $userAuth;
        $this->account = $account;
    }

    /**
     * @OA\Get(
     *     path="/user/group/all",
     *     operationId="all",
     *     summary="List all groups.",
     *     description="Needs role: app-admin, group-admin or user-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of groups.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Group"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function all(): ResponseInterface
    {
        return $this->withJson($this->repositoryFactory->getGroupRepository()->findBy([], ['name' => 'ASC']));
    }

    /**
     * @OA\Get(
     *     path="/user/group/public",
     *     operationId="userGroupPublic",
     *     summary="List all public groups.",
     *     description="Needs role: user",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of groups.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Group"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function public(): ResponseInterface
    {
        $publicGroups = $this->repositoryFactory->getGroupRepository()->findBy(
            ['visibility' => Group::VISIBILITY_PUBLIC],
            ['name' => 'ASC']
        );

        // check required groups
        $groups = [];
        $player = $this->getUser($this->userAuth)->getPlayer();
        foreach ($publicGroups as $publicGroup) {
            $allowed = true;
            foreach ($publicGroup->getRequiredGroups() as $requiredGroup) {
                if (!$player->hasGroup($requiredGroup->getId())) {
                    $allowed = false;
                    break;
                }
            }
            if ($allowed) {
                $groups[] = $publicGroup;
            }
        }

        return $this->withJson($groups);
    }

    /**
     * @OA\Post(
     *     path="/user/group/create",
     *     operationId="create",
     *     summary="Create a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="name",
     *                     description="Name of the group.",
     *                     type="string",
     *                     maxLength=64,
     *                     pattern="^[-._a-zA-Z0-9]+$"
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="The new group.",
     *         @OA\JsonContent(ref="#/components/schemas/Group")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Group name is invalid."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="409",
     *         description="A group with this name already exists."
     *     )
     * )
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $name = $this->getBodyParam($request, 'name', '');
        if (!preg_match($this->namePattern, $name)) {
            return $this->response->withStatus(400);
        }

        if ($this->otherGroupExists($name)) {
            return $this->response->withStatus(409);
        }

        $newGroup = new Group();
        $newGroup->setName($name);

        $this->objectManager->persist($newGroup);

        return $this->flushAndReturn(201, $newGroup);
    }

    /**
     * @OA\Put(
     *     path="/user/group/{id}/rename",
     *     operationId="rename",
     *     summary="Rename a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
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
     *                     description="New name for the group.",
     *                     type="string",
     *                     maxLength=64,
     *                     pattern="^[-._a-zA-Z0-9]+$"
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Group was renamed.",
     *         @OA\JsonContent(ref="#/components/schemas/Group")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Group name is invalid."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Group not found."
     *     ),
     *     @OA\Response(
     *         response="409",
     *         description="A group with this name already exists."
     *     )
     * )
     */
    public function rename(string $id, ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        $name = $this->getBodyParam($request, 'name', '');
        if (!preg_match($this->namePattern, $name)) {
            return $this->response->withStatus(400);
        }

        if ($this->otherGroupExists($name, $this->group->getId())) {
            return $this->response->withStatus(409);
        }

        $this->group->setName($name);

        return $this->flushAndReturn(200, $this->group);
    }

    /**
     * @OA\Put(
     *     path="/user/group/{id}/update-description",
     *     operationId="userGroupUpdateDescription",
     *     summary="Update group description.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"description"},
     *                 @OA\Property(
     *                     property="description",
     *                     description="The description for the group.",
     *                     type="string",
     *                     maxLength=1024
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Description was updated.",
     *         @OA\JsonContent(ref="#/components/schemas/Group")
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
    public function updateDescription(string $id, ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        $description = $this->getBodyParam($request, 'description', '');
        $this->group->setDescription($description);

        return $this->flushAndReturn(200, $this->group);
    }

    /**
     * @OA\Put(
     *     path="/user/group/{id}/set-visibility/{choice}",
     *     operationId="setVisibility",
     *     summary="Change visibility of a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="choice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", enum={"private", "public"})
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Visibility changed.",
     *         @OA\JsonContent(ref="#/components/schemas/Group")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid 'choice' parameter."
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
    public function setVisibility(string $id, string $choice): ResponseInterface
    {
        if (!$this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        try {
            $this->group->setVisibility($choice);
        } catch (\Exception $e) {
            return $this->response->withStatus(400);
        }

        return $this->flushAndReturn(200, $this->group);
    }

    /**
     * @OA\Put(
     *     path="/user/group/{id}/set-auto-accept/{choice}",
     *     operationId="userGroupSetAutoAccept",
     *     summary="Change the auto-accept setting of a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="choice",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", enum={"on", "off"})
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Auto-accept changed.",
     *         @OA\JsonContent(ref="#/components/schemas/Group")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid 'choice' parameter."
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
    public function setAutoAccept(string $id, string $choice): ResponseInterface
    {
        if (!$this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        if (!in_array($choice, ['on', 'off'])) {
            return $this->response->withStatus(400);
        }

        $this->group->setAutoAccept($choice === 'on');

        return $this->flushAndReturn(200, $this->group);
    }

    /**
     * @OA\Delete(
     *     path="/user/group/{id}/delete",
     *     operationId="delete",
     *     summary="Delete a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group was deleted."
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
    public function delete(string $id): ResponseInterface
    {
        if (!$this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        $this->objectManager->remove($this->group);

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Get(
     *     path="/user/group/{id}/managers",
     *     operationId="managers",
     *     summary="List all managers of a group.",
     *     description="Needs role: group-admin, group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name, and roles for users with group-admin
                           role, properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
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
    public function managers(string $id): ResponseInterface
    {
        $user = $this->getUser($this->userAuth)->getPlayer();
        $withRole = $user->hasRole(Role::GROUP_ADMIN);

        return $this->getPlayersFromGroup($id, self::TYPE_MANAGERS, false, $withRole);
    }

    /**
     * @OA\Get(
     *     path="/user/group/{id}/corporations",
     *     operationId="corporations",
     *     summary="List all corporations of a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of corporations ordered by name.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
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
    public function corporations(string $id): ResponseInterface
    {
        if (!$this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($this->group->getCorporations());
    }

    /**
     * @OA\Get(
     *     path="/user/group/{id}/alliances",
     *     operationId="alliances",
     *     summary="List all alliances of a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of alliances ordered by name.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Alliance"))
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
    public function alliances(string $id): ResponseInterface
    {
        if (!$this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($this->group->getAlliances());
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/group/{id}/required-groups",
     *     operationId="requiredGroups",
     *     summary="List all required groups of a group.",
     *     description="Needs role: group-admin, group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of groups ordered by name.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Group"))
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
    public function requiredGroups(string $id): ResponseInterface
    {
        if (!$this->findGroup($id)) {
            return $this->response->withStatus(404, self::ERROR_GROUP_NOT_FOUND);
        }

        return $this->withJson($this->group->getRequiredGroups());
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/group/{id}/add-required/{groupId}",
     *     operationId="addRequiredGroup",
     *     summary="Add required group to a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="ID of the group to add.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Group(s) not found."
     *     )
     * )
     */
    public function addRequiredGroup(string $id, string $groupId): ResponseInterface
    {
        $requiredGroup = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);
        if (!$this->findGroup($id) || ! $requiredGroup) {
            return $this->response->withStatus(404, 'Group(s) not found.');
        }

        $hasGroup = false;
        foreach ($this->group->getRequiredGroups() as $existingGroup) {
            if ($existingGroup->getId() === (int) $groupId) {
                $hasGroup = true;
                break;
            }
        }
        if (!$hasGroup) {
            $this->group->addRequiredGroup($requiredGroup);
        }

        return $this->flushAndReturn(204, $this->group);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/group/{id}/remove-required/{groupId}",
     *     operationId="removeRequiredGroup",
     *     summary="Remove required group from a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="ID of the group to remove.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Group(s) not found."
     *     )
     * )
     */
    public function removeRequiredGroup(string $id, string $groupId): ResponseInterface
    {
        if (!$this->findGroup($id)) {
            return $this->response->withStatus(404, self::ERROR_GROUP_NOT_FOUND);
        }

        $removed = false;
        foreach ($this->group->getRequiredGroups() as $requiredGroup) {
            if ($requiredGroup->getId() === (int) $groupId) {
                $this->group->removeRequiredGroup($requiredGroup);
                $removed = true;
                break;
            }
        }

        if (!$removed) {
            return $this->response->withStatus(404, self::ERROR_GROUP_NOT_FOUND);
        }

        return $this->flushAndReturn(204, $this->group);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/group/{id}/add-manager/{pid}",
     *     operationId="addManager",
     *     summary="Assign a player as manager to a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
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
     *         description="Player and/or group not found."
     *     )
     * )
     */
    public function addManager(string $id, string $pid, Account $account): ResponseInterface
    {
        return $this->addPlayerAs($id, $pid, 'manager', false, $account);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/group/{id}/remove-manager/{pid}",
     *     operationId="removeManager",
     *     summary="Remove a manager (player) from a group.",
     *     description="Needs role: group-admin",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
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
     *         description="Player and/or group not found."
     *     )
     * )
     */
    public function removeManager(string $id, string $pid, Account $account): ResponseInterface
    {
        return $this->removePlayerFrom($id, $pid, self::TYPE_MANAGERS, false, $account);
    }

    /**
     * @OA\Get(
     *     path="/user/group/{id}/applications",
     *     operationId="applications",
     *     summary="List all applications of a group.",
     *     description="Needs role: group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of group applications ordered by created date.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/GroupApplication"))
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
    public function applications(string $id): ResponseInterface
    {
        if (!$this->findGroup($id)) {
            return $this->response->withStatus(404);
        }

        if (!$this->checkManager($this->group)) {
            return $this->response->withStatus(403);
        }

        $apps = $this->repositoryFactory->getGroupApplicationRepository()->findBy(['group' => $id]);

        return $this->withJson($apps);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/group/accept-application/{id}",
     *     operationId="acceptApplication",
     *     summary="Accept a player's request to join a group.",
     *     description="Needs role: group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the application.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Application accepted."
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
    public function acceptApplication(string $id): ResponseInterface
    {
        return $this->handleApplication($id, 'accept');
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/group/deny-application/{id}",
     *     operationId="denyApplication",
     *     summary="Deny a player's request to join a group.",
     *     description="Needs role: group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the application.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Application denied."
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
    public function denyApplication(string $id): ResponseInterface
    {
        return $this->handleApplication($id, 'deny');
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/group/{id}/add-member/{pid}",
     *     operationId="addMember",
     *     summary="Adds a player to a group.",
     *     description="Needs role: group-manager or user-manager",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
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
     *         description="Player added."
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="This player is not a member of the required group(s)."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Player and/or group not found."
     *     )
     * )
     */
    public function addMember(string $id, string $pid, Account $account): ResponseInterface
    {
        return $this->addPlayerAs($id, $pid, 'member', true, $account);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/group/{id}/remove-member/{pid}",
     *     operationId="removeMember",
     *     summary="Remove player from a group.",
     *     description="Needs role: group-manager or user-manager",
     *     tags={"Group"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
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
     *         description="Player removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Player and/or group not found."
     *     )
     * )
     */
    public function removeMember(string $id, string $pid, Account $account): ResponseInterface
    {
        return $this->removePlayerFrom($id, $pid, self::TYPE_MEMBERS, true, $account);
    }

    /**
     * @OA\Get(
     *     path="/user/group/{id}/members",
     *     operationId="members",
     *     summary="List all members of a group.",
     *     description="Needs role: group-admin, group-manager",
     *     tags={"Group"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of players ordered by name. Only id and name properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
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
    public function members(string $id): ResponseInterface
    {
        $user = $this->getUser($this->userAuth)->getPlayer();
        $onlyIfManager = ! $user->hasRole(Role::GROUP_ADMIN);

        return $this->getPlayersFromGroup($id, self::TYPE_MEMBERS, $onlyIfManager, false);
    }

    /**
     * Returns true if another group with that name already exists.
     *
     * @param string $name Group name.
     * @param int|null $id Group ID.
     * @return boolean
     */
    private function otherGroupExists(string $name, int $id = null): bool
    {
        $otherGroup = $this->repositoryFactory->getGroupRepository()->findOneBy(['name' => $name]);

        if ($otherGroup === null) {
            return false;
        }

        if ($otherGroup->getId() === $id) {
            return false;
        }

        return true;
    }

    private function getPlayersFromGroup(
        string $groupId,
        string $type,
        bool $onlyIfManager,
        bool $withRoles
    ): ResponseInterface {
        if (!$this->findGroup($groupId)) {
            return $this->response->withStatus(404);
        }

        if ($onlyIfManager && ! $this->checkManager($this->group)) {
            return $this->response->withStatus(403);
        }

        $players = [];
        if ($type === self::TYPE_MANAGERS) {
            $players = $this->group->getManagers();
        } elseif ($type === self::TYPE_MEMBERS) {
            $players = $this->group->getPlayers();
        }

        $ret = [];
        foreach ($players as $player) {
            $result = $player->jsonSerialize(true);
            if ($withRoles) {
                $result['roles'] = $player->getRoles();
            }
            $ret[] = $result;
        }

        return $this->withJson($ret);
    }

    private function addPlayerAs(
        string $groupId,
        string $playerId,
        string $type,
        bool $onlyIfManager,
        Account $account
    ): ResponseInterface {
        if (!$this->findGroupAndPlayer($groupId, $playerId)) {
            return $this->response->withStatus(404);
        }

        if ($onlyIfManager && ! $this->checkManager($this->group) && ! $this->isUserManager()) {
            return $this->response->withStatus(403);
        }

        if ($type === 'manager' && ! $this->player->hasManagerGroup($this->group)) {
            $this->group->addManager($this->player); // needed to persist
            $this->player->addManagerGroup($this->group); // needed for check in syncManagerRole()
            $account->syncManagerRole($this->player, Role::GROUP_MANAGER);
        } elseif ($type === 'member' && !$this->player->hasGroup($this->group->getId())) {
            foreach ($this->group->getRequiredGroups() as $requiredGroup) {
                if (!$this->player->hasGroup($requiredGroup->getId())) {
                    return $this->response->withStatus(400);
                }
            }
            $this->player->addGroup($this->group);
            $this->account->syncTrackingRole($this->player);
            $this->account->syncWatchlistRole($this->player);
            $this->account->syncWatchlistManagerRole($this->player);
        }

        return $this->flushAndReturn(204);
    }

    private function removePlayerFrom(
        string $groupId,
        string $playerId,
        string $type,
        bool $onlyIfManager,
        Account $account
    ): ResponseInterface {
        if (!$this->findGroupAndPlayer($groupId, $playerId)) {
            return $this->response->withStatus(404);
        }

        if ($onlyIfManager && ! $this->checkManager($this->group) && ! $this->isUserManager()) {
            return $this->response->withStatus(403);
        }

        if ($type === self::TYPE_MANAGERS) {
            $this->group->removeManager($this->player); // needed to persist
            $this->player->removeManagerGroup($this->group); // needed for check in syncManagerRole()
            $account->syncManagerRole($this->player, Role::GROUP_MANAGER);
        } elseif ($type === self::TYPE_MEMBERS) {
            $this->player->removeGroup($this->group);
            $this->account->syncTrackingRole($this->player);
            $this->account->syncWatchlistRole($this->player);
            $this->account->syncWatchlistManagerRole($this->player);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @param string $id application ID
     * @param string $action "accept" or "deny"
     * @return ResponseInterface
     */
    private function handleApplication(string $id, string $action): ResponseInterface
    {
        $app = $this->repositoryFactory->getGroupApplicationRepository()->find($id);

        if (!$app) {
            return $this->response->withStatus(404);
        }

        if (!$this->checkManager($app->getGroup())) {
            return $this->response->withStatus(403);
        }

        if ($action === 'accept') {
            $app->setStatus(GroupApplication::STATUS_ACCEPTED);
            if (!$app->getPlayer()->hasGroup($app->getGroup()->getId())) {
                $app->getPlayer()->addGroup($app->getGroup());
                $this->account->syncTrackingRole($app->getPlayer());
                $this->account->syncWatchlistRole($app->getPlayer());
                $this->account->syncWatchlistManagerRole($app->getPlayer());
            }
        } elseif ($action === 'deny') {
            $app->setStatus(GroupApplication::STATUS_DENIED);
        }

        return $this->flushAndReturn(204);
    }

    private function findGroup(string $id): bool
    {
        $groupEntity = $this->repositoryFactory->getGroupRepository()->find((int) $id);
        if ($groupEntity === null) {
            return false;
        }
        $this->group = $groupEntity;

        return true;
    }

    private function findGroupAndPlayer(string $groupId, string $playerId): bool
    {
        $playerEntity = $this->repositoryFactory->getPlayerRepository()->find((int) $playerId);
        if (!$this->findGroup($groupId) || $playerEntity === null) {
            return false;
        }
        $this->player = $playerEntity;

        return true;
    }

    /**
     * Checks if current logged in user is manager of a group.
     *
     * @param Group $group
     * @return boolean
     */
    private function checkManager(Group $group): bool
    {
        $currentPlayer = $this->getUser($this->userAuth)->getPlayer();
        foreach ($currentPlayer->getManagerGroups() as $mg) {
            if ($mg->getId() === $group->getId()) {
                return true;
            }
        }

        return false;
    }

    private function isUserManager(): bool
    {
        return $this->getUser($this->userAuth)->getPlayer()->hasRole(Role::USER_MANAGER);
    }
}
