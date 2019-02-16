<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Api\BaseController;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\EsiData;
use Brave\Core\Service\ObjectManager;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @SWG\Tag(
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
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var \Brave\Core\Entity\Corporation
     */
    private $corp;

    /**
     * @var \Brave\Core\Entity\Group
     */
    private $group;

    public function __construct(
        Response $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log
    ) {
        parent::__construct($response, $objectManager);

        $this->repositoryFactory = $repositoryFactory;
        $this->log = $log;
    }

    /**
     * @SWG\Get(
     *     path="/user/corporation/all",
     *     operationId="all",
     *     summary="List all corporations.",
     *     description="Needs role: group-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of corporations.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Corporation"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function all(): Response
    {
        return $this->response->withJson(
            $this->repositoryFactory->getCorporationRepository()->findBy([], ['name' => 'ASC']));
    }

    /**
     * @SWG\Get(
     *     path="/user/corporation/with-groups",
     *     operationId="withGroups",
     *     summary="List all corporations that have groups assigned.",
     *     description="Needs role: group-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of corporations (this one includes the groups property).",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Corporation"))
     *     ),
     *     @SWG\Response(
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
     * @SWG\Post(
     *     path="/user/corporation/add/{id}",
     *     operationId="add",
     *     summary="Add an EVE corporation to the database.",
     *     description="Needs role: group-admin
     *                  This makes an ESI request and adds the corporation only if it exists.
     *                  Also adds the corresponding alliance, if there is one.",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="201",
     *         description="The new corporation.",
     *         @SWG\Schema(ref="#/definitions/Corporation")
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid corporation ID."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Corporation not found."
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="The corporation already exists."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @SWG\Response(
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
     * @SWG\Put(
     *     path="/user/corporation/{id}/add-group/{gid}",
     *     operationId="addGroup",
     *     summary="Add a group to the corporation.",
     *     description="Needs role: group-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="gid",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Group added."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Corporation and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
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
     * @SWG\Put(
     *     path="/user/corporation/{id}/remove-group/{gid}",
     *     operationId="removeGroup",
     *     summary="Remove a group from the corporation.",
     *     description="Needs role: group-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="gid",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Group removed."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Corporation and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
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
     * @SWG\Get(
     *     path="/user/corporation/tracked-corporations",
     *     operationId="trackedCorporations",
     *     summary="Returns all corporations that have member tracking data.",
     *     description="Needs role: tracking",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of characters.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Corporation"))
     *     ),
     *     @SWG\Response(
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
     * @SWG\Get(
     *     path="/user/corporation/{id}/members",
     *     operationId="members",
     *     summary="Returns tracking data of corporation members.",
     *     description="Needs role: tracking",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="inactive",
     *         in="query",
     *         description="Limit to members who have been inactive for x days or longer.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="active",
     *         in="query",
     *         description="Limit to members who were active in the last x days.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of corporation members.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/CorporationMember"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function members(string $id, Request $request)
    {
        $inactive = (int) $request->getParam('inactive', 0);
        $active = (int) $request->getParam('active', 0);

        $members = $this->repositoryFactory
            ->getCorporationMemberRepository()
            ->findByLogonDate((int) $id, $inactive, $active);

        return $this->response->withJson($members);
    }

    private function findCorpAndGroup(string $corpId, string $groupId): bool
    {
        $this->corp = $this->repositoryFactory->getCorporationRepository()->find((int) $corpId);
        $this->group = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);

        if ($this->corp === null || $this->group === null) {
            return false;
        }

        return true;
    }
}
