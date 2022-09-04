<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Corporation;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Account;
use Neucore\Service\EsiData;
use Neucore\Service\ObjectManager;
use Neucore\Service\UserAuth;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="Corporation",
 *     description="Corporation management (for automatic group assignment) and tracking."
 * )
 * @OA\Schema(
 *     schema="TrackingDirector",
 *     required={"id", "name"},
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string"
 *     )
 * )
 */
class CorporationController extends BaseController
{
    private UserAuth $userAuth;

    private Account $accountService;

    private Corporation $corp;

    private Group $group;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        UserAuth $userAuth,
        Account $account
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->userAuth = $userAuth;
        $this->accountService = $account;
    }

    /**
     * @OA\Get(
     *     path="/user/corporation/all",
     *     operationId="userCorporationAll",
     *     summary="List all corporations.",
     *     description="Needs role: group-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of corporations.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function all(): ResponseInterface
    {
        return $this->withJson(
            $this->repositoryFactory->getCorporationRepository()->findBy([], ['name' => 'ASC'])
        );
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/corporation/with-groups",
     *     operationId="userCorporationWithGroups",
     *     summary="List all corporations that have groups assigned.",
     *     description="Needs role: group-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of corporations (this one includes the groups property).",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function withGroups(): ResponseInterface
    {
        $result = [];
        foreach ($this->repositoryFactory->getCorporationRepository()->getAllWithGroups() as $corp) {
            // corporation model with groups
            $json = $corp->jsonSerialize();
            $json['groups'] = $corp->getGroups();
            $result[] = $json;
        }

        return $this->withJson($result);
    }

    /**
     * @OA\Post(
     *     path="/user/corporation/add/{id}",
     *     operationId="userCorporationAdd",
     *     summary="Add an EVE corporation to the database.",
     *     description="Needs role: group-admin, watchlist-manager
     *                  This makes an ESI request and adds the corporation only if it exists.
     *                  Also adds the corresponding alliance, if there is one.",
     *     tags={"Corporation"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="The new corporation.",
     *         @OA\JsonContent(ref="#/components/schemas/Corporation")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid corporation ID."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Corporation not found."
     *     ),
     *     @OA\Response(
     *         response="409",
     *         description="The corporation already exists."
     *     ),
     *     @OA\Response(
     *         response="503",
     *         description="ESI request failed."
     *     )
     * )
     */
    public function add(string $id, EsiData $service): ResponseInterface
    {
        $corpId = (int) $id;

        if ($this->repositoryFactory->getCorporationRepository()->find($corpId)) {
            return $this->response->withStatus(409);
        }

        // get corporation
        $corporation = $service->fetchCorporation($corpId, false);
        if ($corporation === null) {
            $code = $service->getLastErrorCode();
            if ($code === 404 || $code === 400) {
                return $this->response->withStatus($code);
            } else {
                return $this->response->withStatus(503);
            }
        }

        // fetch alliance
        if ($corporation->getAlliance() !== null) {
            $service->fetchAlliance($corporation->getAlliance()->getId(), false);
        }

        return $this->flushAndReturn(201, $corporation);
    }

    /**
     * @OA\Put(
     *     path="/user/corporation/{id}/add-group/{gid}",
     *     operationId="userCorporationAddGroup",
     *     summary="Add a group to the corporation.",
     *     description="Needs role: group-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
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
     *         description="Group added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Corporation and/or group not found."
     *     )
     * )
     */
    public function addGroup(string $id, string $gid): ResponseInterface
    {
        if (! $this->findCorpAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        if (! $this->corp->hasGroup($this->group->getId())) {
            $this->corp->addGroup($this->group);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Put(
     *     path="/user/corporation/{id}/remove-group/{gid}",
     *     operationId="userCorporationRemoveGroup",
     *     summary="Remove a group from the corporation.",
     *     description="Needs role: group-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
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
     *         description="Group removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Corporation and/or group not found."
     *     )
     * )
     */
    public function removeGroup(string $id, string $gid): ResponseInterface
    {
        if (! $this->findCorpAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        $this->corp->removeGroup($this->group);

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/corporation/{id}/tracking-director",
     *     operationId="corporationTrackingDirector",
     *     summary="Returns a list of directors with an ESI token for this corporation.",
     *     description="Needs role: tracking-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of directors.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/TrackingDirector"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function trackingDirector(string $id): ResponseInterface
    {
        $repository = $this->repositoryFactory->getEsiTokenRepository();

        $directors = [];
        foreach ($repository->findByLoginAndCorporation(EveLogin::NAME_TRACKING, (int)$id) as $esiToken) {
            $directors[] = [
                'id' => $esiToken->getCharacter() ? $esiToken->getCharacter()->getId() : null,
                'name' => $esiToken->getCharacter() ? $esiToken->getCharacter()->getName() : null,
            ];
        }

        return $this->withJson($directors);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/corporation/{id}/get-groups-tracking",
     *     operationId="getGroupsTracking",
     *     summary="Returns required groups to view member tracking data.",
     *     description="Needs role: tracking-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of groups.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Group"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Corporation not found."
     *     )
     * )
     */
    public function getGroupsTracking(string $id): ResponseInterface
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find((int) $id);

        if ($corporation === null) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($corporation->getGroupsTracking());
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/corporation/{id}/add-group-tracking/{groupId}",
     *     operationId="addGroupTracking",
     *     summary="Add a group to the corporation for member tracking permission.",
     *     description="Needs role: tracking-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
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
     *         description="Corporation and/or group not found."
     *     )
     * )
     */
    public function addGroupTracking(string $id, string $groupId): ResponseInterface
    {
        if (! $this->findCorpAndGroup($id, $groupId)) {
            return $this->response->withStatus(404);
        }

        if (! $this->corp->hasGroupTracking($this->group->getId())) {
            $this->corp->addGroupTracking($this->group);
            $this->accountService->syncTrackingRole(null, $this->corp);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/corporation/{id}/remove-group-tracking/{groupId}",
     *     operationId="removeGroupTracking",
     *     summary="Remove a group for member tracking permission from the corporation.",
     *     description="Needs role: tracking-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
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
     *         description="Corporation and/or group not found."
     *     )
     * )
     */
    public function removeGroupTracking(string $id, string $groupId): ResponseInterface
    {
        if (! $this->findCorpAndGroup($id, $groupId)) {
            return $this->response->withStatus(404);
        }

        $this->corp->removeGroupTracking($this->group);
        $this->accountService->syncTrackingRole(null, $this->corp);

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/corporation/tracked-corporations",
     *     operationId="corporationTrackedCorporations",
     *     summary="Returns corporations that have member tracking data.",
     *     description="Needs role: tracking and membership in appropriate group",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of corporations.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function trackedCorporations(): ResponseInterface
    {
        $corporations = $this->repositoryFactory->getCorporationRepository()->getAllWithMemberTrackingData();

        $result = [];
        foreach ($corporations as $corporation) {
            if ($this->checkPermission($corporation)) {
                $result[] = $corporation->jsonSerialize(true);
            }
        }

        return $this->withJson($result);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/corporation/all-tracked-corporations",
     *     operationId="corporationAllTrackedCorporations",
     *     summary="Returns all corporations that have member tracking data.",
     *     description="Needs role: tracking-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of corporations.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allTrackedCorporations(): ResponseInterface
    {
        $corporations = $this->repositoryFactory->getCorporationRepository()->getAllWithMemberTrackingData();

        return $this->withJson(array_map(function (Corporation $corporation) {
            return $corporation->jsonSerialize(true);
        }, $corporations));
    }

    /**
     * @OA\Get(
     *     path="/user/corporation/{id}/members",
     *     operationId="members",
     *     summary="Returns tracking data of corporation members.",
     *     description="Needs role: tracking and membership in appropriate group",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="inactive",
     *         in="query",
     *         description="Limit to members who have been inactive for x days or longer.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Limit to members who were active in the last x days.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="account",
     *         in="query",
     *         description="Limit to members with (true) or without (false) an account.",
     *         @OA\Schema(type="string", enum={"true", "false"})
     *     ),
     *     @OA\Parameter(
     *         name="valid-token",
     *         in="query",
     *         description="Limit to characters with a valid (true) or invalid (false) token.",
     *         @OA\Schema(type="string", enum={"true", "false"})
     *     ),
     *     @OA\Parameter(
     *         name="token-status-changed",
     *         in="query",
     *         description="Limit to characters whose ESI token status has not changed for x days.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="mail-count",
     *         in="query",
     *         description="Limit to characters whose 'missing player' mail count is greater than or equal to x.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of corporation members.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/CorporationMember"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function members(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find((int) $id);
        if (! $corporation || !$this->checkPermission($corporation)) {
            return $this->response->withStatus(403);
        }

        $inactive = $this->getQueryParam($request, 'inactive');
        $active = $this->getQueryParam($request, 'active');
        $accountParam = $this->getQueryParam($request, 'account');
        $validTokenParam = $this->getQueryParam($request, 'valid-token');
        $tokenStatusChanged = $this->getQueryParam($request, 'token-status-changed');
        $mailCount = $this->getQueryParam($request, 'mail-count');

        if ($accountParam === 'true') {
            $account = true;
        } else {
            $account = $accountParam === 'false' ? false : null;
        }
        if ($validTokenParam === 'true') {
            $validToken = true;
        } else {
            $validToken = $validTokenParam === 'false' ? false : null;
        }

        $members = $this->repositoryFactory
            ->getCorporationMemberRepository()
            ->setInactive($inactive !== null ? (int) $inactive : null)
            ->setActive($active !== null ? (int) $active : null)
            ->setAccount($account)
            ->setValidToken($validToken)
            ->setTokenChanged($tokenStatusChanged !== null ? (int) $tokenStatusChanged : null)
            ->setMailCount((int) $mailCount)
            ->findMatching((int) $id);

        return $this->withJson($members);
    }

    private function findCorpAndGroup(string $corpId, string $groupId): bool
    {
        $corpEntity = $this->repositoryFactory->getCorporationRepository()->find((int) $corpId);
        $groupEntity = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);

        if ($corpEntity === null || $groupEntity === null) {
            return false;
        }

        $this->corp = $corpEntity;
        $this->group = $groupEntity;

        return true;
    }

    /**
     * Checks if logged in user is member of a group that may see the member tracking data of a corporation.
     */
    private function checkPermission(Corporation $corporation): bool
    {
        $playerGroups = $this->getUser($this->userAuth)->getPlayer()->getGroupIds();
        foreach ($corporation->getGroupsTracking() as $group) {
            if (in_array($group->getId(), $playerGroups)) {
                return true;
            }
        }
        return false;
    }
}
