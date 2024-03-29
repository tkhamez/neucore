<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Alliance;
use Neucore\Entity\Group;
use Neucore\Service\EsiData;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="Alliance",
 *     description="Alliance management (for automatic group assignment)."
 * )
 */
class AllianceController extends BaseController
{
    private Alliance $alliance;

    private Group $group;

    /**
     * @OA\Get(
     *     path="/user/alliance/find/{query}",
     *     operationId="userAllianceFind",
     *     summary="Returns a list of alliances that matches the query (partial matching name or ticker).",
     *     description="Needs role: group-admin, watchlist-manager, settings",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="path",
     *         required=true,
     *         description="Name or ticker of the alliance (min. 3 characters).",
     *         @OA\Schema(type="string", minLength=3)
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of alliances.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Alliance"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized"
     *     )
     * )
     */
    public function find(string $query): ResponseInterface
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return $this->withJson([]);
        }

        $result = $this->repositoryFactory->getAllianceRepository()->findByNameOrTickerPartialMatch($query);

        return $this->withJson($result);
    }

    /**
     * @OA\Post(
     *     path="/user/alliance/alliances",
     *     operationId="userAllianceAlliances",
     *     summary="Returns alliances found by ID.",
     *     description="Needs role: group-admin, watchlist-manager, settings",
     *     tags={"Alliance"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="EVE IDs of alliances.",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(type="array", @OA\Items(type="integer"))
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of alliances.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Alliance"))
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid body."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function alliances(ServerRequestInterface $request): ResponseInterface
    {
        $ids = $this->getIntegerArrayFromBody($request);

        if ($ids === null) {
            return $this->response->withStatus(400);
        }
        if (empty($ids)) {
            return $this->withJson([]);
        }

        $result = $this->repositoryFactory->getAllianceRepository()->findBy(['id' => $ids], ['name' => 'ASC']);

        return $this->withJson($result);
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
     *     security={{"Session"={}, "CSRF"={}}},
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
        $newAlliance = $service->fetchAlliance($allianceId, false);
        if ($newAlliance === null) {
            $code = $service->getLastErrorCode();
            if ($code === 404 || $code === 400) {
                return $this->response->withStatus($code);
            } else {
                return $this->response->withStatus(503);
            }
        }

        return $this->flushAndReturn(201, $newAlliance);
    }

    /**
     * @OA\Put(
     *     path="/user/alliance/{id}/add-group/{gid}",
     *     operationId="addGroup",
     *     summary="Add a group to the alliance.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}, "CSRF"={}}},
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
     *     security={{"Session"={}, "CSRF"={}}},
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
        $allianceEntity = $this->repositoryFactory->getAllianceRepository()->find((int) $allianceId);
        $groupEntity = $this->repositoryFactory->getGroupRepository()->find((int) $groupId);

        if ($allianceEntity === null || $groupEntity === null) {
            return false;
        }

        $this->alliance = $allianceEntity;
        $this->group = $groupEntity;

        return true;
    }
}
