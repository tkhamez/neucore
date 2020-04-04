<?php declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Alliance;
use Neucore\Entity\Group;
use Neucore\Service\EsiData;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * @OA\Tag(
 *     name="Alliance",
 *     description="Alliance management (for automatic group assignment)."
 * )
 */
class AllianceController extends BaseController
{
    /**
     * @var Alliance
     */
    private $alliance;

    /**
     * @var Group
     */
    private $group;

    /**
     * @OA\Get(
     *     path="/user/alliance/all",
     *     operationId="all",
     *     summary="List all alliances.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of alliances.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Alliance"))
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
            $this->repositoryFactory->getAllianceRepository()->findBy([], ['name' => 'ASC'])
        );
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/alliance/with-groups",
     *     operationId="withGroups",
     *     summary="List all alliances that have groups assigned.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of alliances (this one includes the groups property).",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Alliance"))
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
        foreach ($this->repositoryFactory->getAllianceRepository()->getAllWithGroups() as $alliance) {
            // alliance model with groups
            $json = $alliance->jsonSerialize();
            $json['groups'] = $alliance->getGroups();
            $result[] = $json;
        }

        return $this->withJson($result);
    }

    /**
     * @OA\Post(
     *     path="/user/alliance/add/{id}",
     *     operationId="add",
     *     summary="Add an EVE alliance to the database.",
     *     description="Needs role: group-admin, watchlist-manager
     *                  This makes an ESI request and adds the alliance only if it exists",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE alliance ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="The new alliance.",
     *         @OA\JsonContent(ref="#/components/schemas/Alliance")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid alliance ID."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Alliance not found."
     *     ),
     *     @OA\Response(
     *         response="409",
     *         description="The alliance already exists."
     *     ),
     *     @OA\Response(
     *         response="503",
     *         description="ESI request failed."
     *     )
     * )
     */
    public function add(string $id, EsiData $service): ResponseInterface
    {
        $allianceId = (int) $id;

        if ($this->repositoryFactory->getAllianceRepository()->find($allianceId)) {
            return $this->response->withStatus(409);
        }

        // get alliance
        $alliance = $service->fetchAlliance($allianceId, false);
        if ($alliance === null) {
            $code = $service->getLastErrorCode();
            if ($code === 404 || $code === 400) {
                return $this->response->withStatus($code);
            } else {
                return $this->response->withStatus(503);
            }
        }

        return $this->flushAndReturn(201, $alliance);
    }

    /**
     * @OA\Put(
     *     path="/user/alliance/{id}/add-group/{gid}",
     *     operationId="addGroup",
     *     summary="Add a group to the alliance.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the alliance.",
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
     *         description="Alliance and/or group not found."
     *     )
     * )
     */
    public function addGroup(string $id, string $gid): ResponseInterface
    {
        if (! $this->findAllianceAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        if (! $this->alliance->hasGroup($this->group->getId())) {
            $this->alliance->addGroup($this->group);
        }

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Put(
     *     path="/user/alliance/{id}/remove-group/{gid}",
     *     operationId="removeGroup",
     *     summary="Remove a group from the alliance.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the alliance.",
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
     *         description="Alliance and/or group not found."
     *     )
     * )
     */
    public function removeGroup(string $id, string $gid): ResponseInterface
    {
        if (! $this->findAllianceAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        $this->alliance->removeGroup($this->group);

        return $this->flushAndReturn(204);
    }

    private function findAllianceAndGroup(string $allianceId, string $groupId): bool
    {
        $alliance = $this->repositoryFactory->getAllianceRepository()->find((int) $allianceId);
        $group = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);

        if ($alliance === null || $group === null) {
            return false;
        }

        $this->alliance = $alliance;
        $this->group = $group;

        return true;
    }
}
