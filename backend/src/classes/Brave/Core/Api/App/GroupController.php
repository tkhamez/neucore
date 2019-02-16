<?php declare(strict_types=1);

namespace Brave\Core\Api\App;

use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\Account;
use Brave\Core\Service\AppAuth;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Request;
use Slim\Http\Response;

/**
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
class GroupController
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

    /**
     * @var Account
     */
    private $accountService;

    public function __construct(
        Response $response,
        AppAuth $appAuthService,
        RepositoryFactory $repositoryFactory,
        Account $accountService
    ) {
        $this->response = $response;
        $this->appAuthService = $appAuthService;
        $this->repositoryFactory = $repositoryFactory;
        $this->accountService = $accountService;
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/groups/{cid}",
     *     operationId="groupsV1",
     *     summary="Return groups of the character's player account.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
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
     *         description="Character not found. (default reason phrase)"
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
     * @SWG\Get(
     *     path="/app/v2/groups/{cid}",
     *     operationId="groupsV2",
     *     summary="Return groups of the character's player account.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
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
     *         description="Reason phrase: Character not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupsV2(string $cid, ServerRequestInterface $request): Response
    {
        $this->response = $this->groupsV1($cid, $request);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Character not found.');
        }

        return $this->response;
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/groups",
     *     operationId="groupsBulkV1",
     *     summary="Return groups of multiple players, identified by one of their character IDs.",
     *     description="Needs role: app-groups.<br>
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
        return $this->groupsBulkFor('Player', $request);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/corp-groups/{cid}",
     *     operationId="corpGroupsV1",
     *     summary="Return groups of the corporation.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
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
     *         description="Corporation not found. (default reason phrase)"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corpGroupsV1(string $cid, ServerRequestInterface $request): Response
    {
        return $this->corpOrAllianceGroups($cid, 'Corporation', $request);
    }

    /**
     * @SWG\Get(
     *     path="/app/v2/corp-groups/{cid}",
     *     operationId="corpGroupsV2",
     *     summary="Return groups of the corporation.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
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
     *         description="Reason phrase: Corporation not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corpGroupsV2(string $cid, ServerRequestInterface $request): Response
    {
        $this->response = $this->corpGroupsV1($cid, $request);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Corporation not found.');
        }

        return $this->response;
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/corp-groups",
     *     operationId="corpGroupsBulkV1",
     *     summary="Return groups of multiple corporations.",
     *     description="Needs role: app-groups.<br>
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
        return $this->groupsBulkFor('Corporation', $request);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/alliance-groups/{aid}",
     *     operationId="allianceGroupsV1",
     *     summary="Return groups of the alliance.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
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
     *         description="Alliance not found. (default reason phrase)"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allianceGroupsV1(string $aid, ServerRequestInterface $request): Response
    {
        return $this->corpOrAllianceGroups($aid, 'Alliance', $request);
    }

    /**
     * @SWG\Get(
     *     path="/app/v2/alliance-groups/{aid}",
     *     operationId="allianceGroupsV2",
     *     summary="Return groups of the alliance.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
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
     *         description="Reason phrase: Alliance not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allianceGroupsV2(string $aid, ServerRequestInterface $request): Response
    {
        $this->response = $this->allianceGroupsV1($aid, $request);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Alliance not found.');
        }

        return $this->response;
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/alliance-groups",
     *     operationId="allianceGroupsBulkV1",
     *     summary="Return groups of multiple alliances.",
     *     description="Needs role: app-groups.<br>
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
        return $this->groupsBulkFor('Alliance', $request);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/groups-with-fallback",
     *     operationId="groupsWithFallbackV1",
     *     summary="Returns groups from the character's account, if available, or the corporation and alliance.",
     *     description="Needs role: app-groups.<br>
     *                  Returns only groups that have been added to the app as well.<br>
     *                  It is not checked if character, corporation and alliance match.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="character",
     *         in="query",
     *         required=true,
     *         description="EVE character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="corporation",
     *         in="query",
     *         required=true,
     *         description="EVE corporation ID.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="alliance",
     *         in="query",
     *         description="EVE alliance ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupsWithFallbackV1(Request $request): Response
    {
        $characterId = (int) $request->getParam('character');
        $corporationId = (int) $request->getParam('corporation');
        $allianceId = (int) $request->getParam('alliance');

        $appGroups = $this->appAuthService->getApp($request)->getGroups();

        $characterResult = $this->getGroupsForPlayer($characterId, $appGroups);
        if ($characterResult !== null) {
            // could be an empty result
            return $this->response->withJson($characterResult['groups']);
        }

        $fallbackGroups = [];

        $corporationResult = $this->getGroupsFor('Corporation', $corporationId, $appGroups);
        if ($corporationResult !== null) {
            $fallbackGroups = $corporationResult['groups'];
        }

        $allianceResult = $this->getGroupsFor('Alliance', $allianceId, $appGroups);
        if ($allianceResult !== null) {
            // add groups that are not already in the result set
            foreach ($allianceResult['groups'] as $allianceGroup) {
                $addGroup = true;
                foreach ($fallbackGroups as $fallbackGroup) {
                    if ($allianceGroup['id'] === $fallbackGroup['id']) {
                        $addGroup = false;
                    }
                }
                if ($addGroup) {
                    $fallbackGroups[] = $allianceGroup;
                }
            }
        }

        return $this->response->withJson($fallbackGroups);
    }

    private function corpOrAllianceGroups(string $id, string $type, ServerRequestInterface $request)
    {
        $appGroups = $this->appAuthService->getApp($request)->getGroups();
        $result = $this->getGroupsFor($type, (int) $id, $appGroups);

        if ($result === null) {
            return $this->response->withStatus(404);
        }

        return $this->response->withJson($result['groups']);
    }

    /**
     * @param string $type "Player", "Corporation" or "Alliance"
     * @param ServerRequestInterface $request
     * @return Response
     */
    private function groupsBulkFor(string $type, ServerRequestInterface $request): Response
    {
        $ids = $this->getIntegerArrayFromBody($request);
        if ($ids === null) {
            return $this->response->withStatus(400);
        }
        if (count($ids) === 0) {
            return $this->response->withJson([]);
        }

        $appGroups = $this->appAuthService->getApp($request)->getGroups();

        $result = [];
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }

            if ($type === 'Player') {
                $groups = $this->getGroupsForPlayer($id, $appGroups);
            } else {
                $groups = $this->getGroupsFor($type, $id, $appGroups);
            }
            if ($groups === null) {
                continue;
            }

            $result[] = $groups;
        }

        return $this->response->withJson($result);
    }

    private function getIntegerArrayFromBody(ServerRequestInterface $request)
    {
        $ids = $request->getParsedBody();

        if (! is_array($ids)) {
            return null;
        }

        $ids = array_map('intVal', $ids);
        $ids = array_unique($ids);

        return $ids;
    }

    /**
     * @param int $characterId
     * @param \Brave\Core\Entity\Group[] $appGroups
     * @return null|array Returns NULL if character was not found.
     */
    private function getGroupsForPlayer(int $characterId, array $appGroups)
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($char === null || $char->getPlayer() === null) {
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

        if ($this->accountService->groupsDeactivated($char->getPlayer())) {
            return $result;
        }

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
