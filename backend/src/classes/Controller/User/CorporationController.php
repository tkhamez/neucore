<?php declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EsiData;
use Neucore\Service\ObjectManager;
use OpenApi\Annotations as OA;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @OA\Tag(
 *     name="Corporation",
 *     description="Corporation management (for automatic group assignment) and tracking."
 * )
 */
class CorporationController extends BaseController
{
    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var Corporation
     */
    private $corp;

    /**
     * @var Group
     */
    private $group;

    public function __construct(
        Response $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory
    ) {
        parent::__construct($response, $objectManager);

        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @OA\Get(
     *     path="/user/corporation/all",
     *     operationId="all",
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
    public function all(): Response
    {
        return $this->response->withJson(
            $this->repositoryFactory->getCorporationRepository()->findBy([], ['name' => 'ASC'])
        );
    }

    /**
     * @OA\Get(
     *     path="/user/corporation/with-groups",
     *     operationId="withGroups",
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
    public function withGroups(): Response
    {
        $result = [];
        foreach ($this->repositoryFactory->getCorporationRepository()->getAllWithGroups() as $corp) {
            // corporation model with groups
            $json = $corp->jsonSerialize();
            $json['groups'] = $corp->getGroups();
            $result[] = $json;
        }

        return $this->response->withJson($result);
    }

    /**
     * @OA\Post(
     *     path="/user/corporation/add/{id}",
     *     operationId="add",
     *     summary="Add an EVE corporation to the database.",
     *     description="Needs role: group-admin
     *                  This makes an ESI request and adds the corporation only if it exists.
     *                  Also adds the corresponding alliance, if there is one.",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
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
    public function add(string $id, EsiData $service): Response
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
     *     operationId="addGroup",
     *     summary="Add a group to the corporation.",
     *     description="Needs role: group-admin",
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
    public function addGroup(string $id, string $gid): Response
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
     *     operationId="removeGroup",
     *     summary="Remove a group from the corporation.",
     *     description="Needs role: group-admin",
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
    public function removeGroup(string $id, string $gid): Response
    {
        if (! $this->findCorpAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        $this->corp->removeGroup($this->group);

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Get(
     *     path="/user/corporation/tracked-corporations",
     *     operationId="trackedCorporations",
     *     summary="Returns all corporations that have member tracking data.",
     *     description="Needs role: tracking",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of characters.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function trackedCorporations()
    {
        $corporations = $this->repositoryFactory->getCorporationRepository()->getAllWithMemberTrackingData();

        return $this->response->withJson($corporations);
    }

    /**
     * @OA\Get(
     *     path="/user/corporation/{id}/members",
     *     operationId="members",
     *     summary="Returns tracking data of corporation members.",
     *     description="Needs role: tracking",
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
    public function members(string $id, Request $request)
    {
        $inactive = $request->getParam('inactive');
        $active = $request->getParam('active');
        $account = $request->getParam('account');
        $validToken = $request->getParam('valid-token');
        $tokenStatusChanged = $request->getParam('token-status-changed');

        $members = $this->repositoryFactory
            ->getCorporationMemberRepository()
            ->setInactive($inactive !== null ? (int) $inactive : null)
            ->setActive($active !== null ? (int) $active : null)
            ->setAccount($account === 'true' ? true : ($account === 'false' ? false : null))
            ->setValidToken($validToken === 'true' ? true : ($validToken === 'false' ? false : null))
            ->setTokenChanged($tokenStatusChanged !== null ? (int) $tokenStatusChanged : null)
            ->findMatching((int) $id);

        return $this->response->withJson($members);
    }

    private function findCorpAndGroup(string $corpId, string $groupId): bool
    {
        $corp = $this->repositoryFactory->getCorporationRepository()->find((int) $corpId);
        $group = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);

        if ($corp === null || $group === null) {
            return false;
        }

        $this->corp = $corp;
        $this->group = $group;

        return true;
    }
}
