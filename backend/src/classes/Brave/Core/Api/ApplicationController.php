<?php declare(strict_types=1);

namespace Brave\Core\Api;

use Brave\Core\Repository\RepositoryFactory;
use Brave\Core\Service\AppAuth;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;

/**
 *
 * @SWG\Tag(
 *     name="Application",
 *     description="API for 3rd party apps.",
 * )
 *
 * @SWG\Definition(
 *     definition="CharacterGroups",
 *     required={"character", "groups"},
 *     @SWG\Property(
 *         property="character",
 *         ref="#/definitions/Character"
 *     ),
 *     @SWG\Property(
 *         property="groups",
 *         type="array",
 *         @SWG\Items(ref="#/definitions/Group")
 *     )
 * )
 */
class ApplicationController
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var AppAuth
     */
    private $appAuthService;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    public function __construct(Response $response, AppAuth $appAuthService, RepositoryFactory $repositoryFactory)
    {
        $this->response = $response;
        $this->appAuthService = $appAuthService;
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/show",
     *     operationId="showV1",
     *     summary="Show app information.",
     *     description="Needs role: app",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The app information",
     *         @SWG\Schema(ref="#/definitions/App")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function showV1(ServerRequestInterface $request): Response
    {
        return $this->response->withJson($this->appAuthService->getApp($request));
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/groups/{cid}",
     *     operationId="groupsV1",
     *     summary="Return groups of the character's player account.",
     *     description="Needs role: app<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Character not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupsV1(string $cid, ServerRequestInterface $request): Response
    {
        $appGroups = $this->appAuthService->getApp($request)->getGroups();
        $result = $this->getGroupsForPlayer((int) $cid, $appGroups);

        if ($result === null) {
            return $this->response->withStatus(404);
        }

        return $this->response->withJson($result['groups']);
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/groups",
     *     operationId="groupsBulkV1",
     *     summary="Return groups of multiple players, identified by one of their character IDs.",
     *     description="Needs role: app.
     *                  Returns only groups that have been added to the app as well.
     *                  Skips characters that are not found in the local database.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="ids",
     *         in="body",
     *         required=true,
     *         description="EVE character IDs array.",
     *         @SWG\Schema(type="array", @SWG\Items(type="integer"))
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of characters (id, name and corporation properties only) with groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/CharacterGroups"))
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid body."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupsBulkV1(ServerRequestInterface $request): Response
    {
        $charIds = $this->getIntegerArrayFromBody($request);
        if ($charIds === null) {
            return $this->response->withStatus(400);
        }
        if (count($charIds) === 0) {
            return $this->response->withJson([]);
        }

        $appGroups = $this->appAuthService->getApp($request)->getGroups();

        $result = [];
        foreach ($charIds as $charId) {
            if ($charId <= 0) {
                continue;
            }

            $charGroups = $this->getGroupsForPlayer($charId, $appGroups);
            if ($charGroups === null) {
                continue;
            }

            $result[] = $charGroups;
        }

        return $this->response->withJson($result);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/corp-groups/{cid}",
     *     operationId="corpGroupsV1",
     *     summary="Return groups of the corporation.",
     *     description="Needs role: app<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Corporation not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corpGroupsV1(string $cid, ServerRequestInterface $request): Response
    {
        $appGroups = $this->appAuthService->getApp($request)->getGroups();
        $result = $this->getGroupsFor('Corporation', (int) $cid, $appGroups);

        if ($result === null) {
            return $this->response->withStatus(404);
        }

        return $this->response->withJson($result['groups']);
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/corp-groups",
     *     operationId="corpGroupsBulkV1",
     *     summary="Return groups of multiple corporations.",
     *     description="Needs role: app.
     *                  Returns only groups that have been added to the app as well.
     *                  Skips corporations that are not found in the local database.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="ids",
     *         in="body",
     *         required=true,
     *         description="EVE corporation IDs array.",
     *         @SWG\Schema(type="array", @SWG\Items(type="integer"))
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of corporations with groups but without alliance.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Corporation"))
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid body."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corpGroupsBulkV1(ServerRequestInterface $request): Response
    {
        $corpIds = $this->getIntegerArrayFromBody($request);
        if ($corpIds === null) {
            return $this->response->withStatus(400);
        }
        if (count($corpIds) === 0) {
            return $this->response->withJson([]);
        }

        $appGroups = $this->appAuthService->getApp($request)->getGroups();

        $result = [];
        foreach ($corpIds as $corpId) {
            if ($corpId <= 0) {
                continue;
            }

            $corpGroups = $this->getGroupsFor('Corporation', $corpId, $appGroups);
            if ($corpGroups === null) {
                continue;
            }

            $result[] = $corpGroups;
        }

        return $this->response->withJson($result);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/alliance-groups/{aid}",
     *     operationId="allianceGroupsV1",
     *     summary="Return groups of the alliance.",
     *     description="Needs role: app<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="aid",
     *         in="path",
     *         required=true,
     *         description="EVE alliance ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Alliance not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allianceGroupsV1(string $aid, ServerRequestInterface $request): Response
    {
        $appGroups = $this->appAuthService->getApp($request)->getGroups();
        $result = $this->getGroupsFor('Alliance', (int) $aid, $appGroups);

        if ($result === null) {
            return $this->response->withStatus(404);
        }

        return $this->response->withJson($result['groups']);
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/alliance-groups",
     *     operationId="allianceGroupsBulkV1",
     *     summary="Return groups of multiple alliances.",
     *     description="Needs role: app.
     *                  Returns only groups that have been added to the app as well.
     *                  Skips alliances that are not found in the local database.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="ids",
     *         in="body",
     *         required=true,
     *         description="EVE alliance IDs array.",
     *         @SWG\Schema(type="array", @SWG\Items(type="integer"))
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of alliances with groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Alliance"))
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid body."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allianceGroupsBulkV1(ServerRequestInterface $request): Response
    {
        $allianceIds = $this->getIntegerArrayFromBody($request);
        if ($allianceIds === null) {
            return $this->response->withStatus(400);
        }
        if (count($allianceIds) === 0) {
            return $this->response->withJson([]);
        }

        $appGroups = $this->appAuthService->getApp($request)->getGroups();

        $result = [];
        foreach ($allianceIds as $allianceId) {
            if ($allianceId <= 0) {
                continue;
            }

            $allianceGroups = $this->getGroupsFor('Alliance', $allianceId, $appGroups);
            if ($allianceGroups === null) {
                continue;
            }

            $result[] = $allianceGroups;
        }

        return $this->response->withJson($result);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/main/{cid}",
     *     operationId="mainV1",
     *     summary="Returns the main character of the player account to which the character ID belongs.",
     *     description="Needs role: app<br>It is possible that an account has no main character.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The main character",
     *         @SWG\Schema(ref="#/definitions/Character")
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="No main character found."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Character not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function mainV1(string $cid): Response
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int) $cid);

        if ($char === null) {
            return $this->response->withStatus(404);
        }

        $result = null;
        foreach ($char->getPlayer()->getCharacters() as $character) {
            if ($character->getMain()) {
                $result = $character;
            }
        }

        if ($result === null) {
            return $this->response->withStatus(204);
        }

        return $this->response->withJson($result);
    }

    private function getIntegerArrayFromBody(ServerRequestInterface $request)
    {
        $ids = $request->getParsedBody();

        if (! is_array($ids)) {
            return null;
        }

        $ids = array_map('intval', $ids);
        $ids = array_unique($ids);

        return $ids;
    }

    /**
     *
     * @param int $characterId
     * @param \Brave\Core\Entity\Group[] $appGroups
     * @return null|array Returns NULL if character was not found.
     */
    private function getGroupsForPlayer(int $characterId, array $appGroups)
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($char === null) {
            return null;
        }

        $result = [
            'character' => [
                'id' => $char->getId(),
                'name' => $char->getName(),
                'corporation' => $char->getCorporation(),
            ],
            'groups' => []
        ];

        foreach ($appGroups as $appGroup) {
            foreach ($char->getPlayer()->getGroups() as $playerGroup) {
                if ($appGroup->getId() === $playerGroup->getId()) {
                    $result['groups'][] = $playerGroup;
                }
            }
        }

        return $result;
    }

    /**
     * Get groups of corporation or alliance.
     *
     * Returns data from jsonSerialize() of a Corporation or Alliance object
     * plus all of it's groups that also belongs to the app.
     *
     * @param string $entityName "Corporation" or "Alliance"
     * @param int $entityId
     * @param \Brave\Core\Entity\Group[] $appGroups
     * @return null|array Returns NULL if corporation was not found.
     * @see \Brave\Core\Entity\Corporation::jsonSerialize()
     * @see \Brave\Core\Entity\Alliance::jsonSerialize()
     * @see \Brave\Core\Entity\Group::jsonSerialize()
     */
    private function getGroupsFor(string $entityName, int $entityId, array $appGroups)
    {
        $repository = $entityName === 'Corporation' ?
            $this->repositoryFactory->getCorporationRepository() :
            $this->repositoryFactory->getAllianceRepository();

        $entity = $repository->find($entityId);
        if ($entity === null) {
            return null;
        }

        $result = $entity->jsonSerialize();
        if (array_key_exists('alliance', $result)) {
            unset($result['alliance']);
        }
        $result['groups'] = [];

        foreach ($appGroups as $appGroup) {
            foreach ($entity->getGroups() as $corpGroup) {
                if ($appGroup->getId() === $corpGroup->getId()) {
                    $result['groups'][] = $corpGroup->jsonSerialize();
                }
            }
        }

        return $result;
    }
}
