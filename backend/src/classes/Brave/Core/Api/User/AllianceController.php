<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\EsiData;
use Brave\Core\Service\ObjectManager;
use Slim\Http\Response;

/**
 * @SWG\Tag(
 *     name="Alliance",
 *     description="Alliance management (for automatic group assignment)."
 * )
 */
class AllianceController
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var \Brave\Core\Entity\Alliance
     */
    private $alliance;

    /**
     * @var \Brave\Core\Entity\Group
     */
    private $group;

    public function __construct(Response $response, ObjectManager $objectManager, RepositoryFactory $repositoryFactory)
    {
        $this->response = $response;
        $this->objectManager = $objectManager;
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @SWG\Get(
     *     path="/user/alliance/all",
     *     operationId="all",
     *     summary="List all alliances.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of alliances.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Alliance"))
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
            $this->repositoryFactory->getAllianceRepository()->findBy([], ['name' => 'ASC']));
    }

    /**
     * @SWG\Get(
     *     path="/user/alliance/with-groups",
     *     operationId="withGroups",
     *     summary="List all alliances that have groups assigned.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of alliances (this one includes the groups property).",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Alliance"))
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
        foreach ($this->repositoryFactory->getAllianceRepository()->getAllWithGroups() as $alliance) {
            // alliance model with groups
            $json = $alliance->jsonSerialize();
            $json['groups'] = $alliance->getGroups();
            $result[] = $json;
        }

        return $this->response->withJson($result);
    }

    /**
     * @SWG\Post(
     *     path="/user/alliance/add/{id}",
     *     operationId="add",
     *     summary="Add an EVE alliance to the database.",
     *     description="Needs role: group-admin
     *                  This makes an ESI request and adds the alliance only if it exists",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE alliance ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="201",
     *         description="The new alliance.",
     *         @SWG\Schema(ref="#/definitions/Alliance")
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid alliance ID."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Alliance not found."
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="The alliance already exists."
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
        $allianceId = (int) $id;

        if ($this->repositoryFactory->getAllianceRepository()->find($allianceId)) {
            return $this->response->withStatus(409);
        }

        // get alliance
        $alliance = $service->fetchAlliance($allianceId, false);
        if ($alliance === null) {
            $code = $service->getEsiApi()->getLastErrorCode();
            if ($code === 404 || $code === 400) {
                return $this->response->withStatus($code);
            } else {
                return $this->response->withStatus(503);
            }
        }

        if (! $this->objectManager->flush()) {
            return $this->response->withStatus(500);
        }

        return $this->response->withStatus(201)->withJson($alliance);
    }

    /**
     * @SWG\Put(
     *     path="/user/alliance/{id}/add-group/{gid}",
     *     operationId="addGroup",
     *     summary="Add a group to the alliance.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the alliance.",
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
     *         description="Alliance and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function addGroup(string $id, string $gid): Response
    {
        if (! $this->findAllianceAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        if (! $this->alliance->hasGroup($this->group->getId())) {
            $this->alliance->addGroup($this->group);
        }

        if (! $this->objectManager->flush()) {
            return $this->response->withStatus(500);
        }

        return $this->response->withStatus(204);
    }

    /**
     * @SWG\Put(
     *     path="/user/alliance/{id}/remove-group/{gid}",
     *     operationId="removeGroup",
     *     summary="Remove a group from the alliance.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the alliance.",
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
     *         description="Alliance and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function removeGroup(string $id, string $gid): Response
    {
        if (! $this->findAllianceAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        $this->alliance->removeGroup($this->group);

        if (! $this->objectManager->flush()) {
            return $this->response->withStatus(500);
        }

        return $this->response->withStatus(204);
    }

    private function findAllianceAndGroup(string $allianceId, string $groupId): bool
    {
        $this->alliance = $this->repositoryFactory->getAllianceRepository()->find((int) $allianceId);
        $this->group = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);

        if ($this->alliance === null || $this->group === null) {
            return false;
        }

        return true;
    }
}
