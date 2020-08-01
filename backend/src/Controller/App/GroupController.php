<?php

declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Controller\BaseController;
use Neucore\Entity\Group;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Account;
use Neucore\Service\AppAuth;
use Neucore\Service\ObjectManager;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="Application - Groups"
 * )
 *
 * @OA\Schema(
 *     schema="CharacterGroups",
 *     required={"character", "groups"},
 *     @OA\Property(
 *         property="character",
 *         ref="#/components/schemas/Character"
 *     ),
 *     @OA\Property(
 *         property="groups",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Group")
 *     )
 * )
 */
class GroupController extends BaseController
{
    const KEY_GROUPS = 'groups';

    const KEY_ALLIANCE = 'alliance';

    const TYPE_CORPORATION = 'Corporation';

    const TYPE_ALLIANCE = 'Alliance';

    /**
     * @var AppAuth
     */
    private $appAuthService;

    /**
     * @var Account
     */
    private $accountService;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        AppAuth $appAuthService,
        Account $accountService
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);
        
        $this->appAuthService = $appAuthService;
        $this->accountService = $accountService;
    }

    /**
     * @OA\Get(
     *     path="/app/v1/groups/{cid}",
     *     deprecated=true,
     *     operationId="groupsV1",
     *     summary="Return groups of the character's player account.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application - Groups"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
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
     *         description="Character not found. (default reason phrase)"
     *     )
     * )
     */
    public function groupsV1(string $cid, ServerRequestInterface $request): ResponseInterface
    {
        $appGroups = $this->getAppGroups($request);
        $result = $this->getGroupsForPlayer((int) $cid, $appGroups);

        if ($result === null) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($result[self::KEY_GROUPS]);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/app/v2/groups/{cid}",
     *     operationId="groupsV2",
     *     summary="Return groups of the character's player account.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application - Groups"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
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
     *         description="Reason phrase: Character not found."
     *     )
     * )
     */
    public function groupsV2(string $cid, ServerRequestInterface $request): ResponseInterface
    {
        $this->response = $this->groupsV1($cid, $request);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Character not found.');
        }

        return $this->response;
    }

    /**
     * @noinspection PhpUnused
     * @OA\Post(
     *     path="/app/v1/groups",
     *     operationId="groupsBulkV1",
     *     summary="Return groups of multiple players, identified by one of their character IDs.",
     *     description="Needs role: app-groups.<br>
     *                  Returns only groups that have been added to the app as well.
     *                  Skips characters that are not found in the local database.",
     *     tags={"Application - Groups"},
     *     security={{"BearerAuth"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="EVE character IDs array.",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(type="array", @OA\Items(type="integer"))
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of characters (id, name and corporation properties only) with groups.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/CharacterGroups"))
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
    public function groupsBulkV1(ServerRequestInterface $request): ResponseInterface
    {
        return $this->groupsBulkFor('Player', $request);
    }

    /**
     * @OA\Get(
     *     path="/app/v1/corp-groups/{cid}",
     *     deprecated=true,
     *     operationId="corpGroupsV1",
     *     summary="Return groups of the corporation.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application - Groups"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
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
     *         description="Corporation not found. (default reason phrase)"
     *     )
     * )
     */
    public function corpGroupsV1(string $cid, ServerRequestInterface $request): ResponseInterface
    {
        return $this->corpOrAllianceGroups($cid, self::TYPE_CORPORATION, $request);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/app/v2/corp-groups/{cid}",
     *     operationId="corpGroupsV2",
     *     summary="Return groups of the corporation.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application - Groups"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
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
     *         description="Reason phrase: Corporation not found."
     *     )
     * )
     */
    public function corpGroupsV2(string $cid, ServerRequestInterface $request): ResponseInterface
    {
        $this->response = $this->corpGroupsV1($cid, $request);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Corporation not found.');
        }

        return $this->response;
    }

    /**
     * @noinspection PhpUnused
     * @OA\Post(
     *     path="/app/v1/corp-groups",
     *     operationId="corpGroupsBulkV1",
     *     summary="Return groups of multiple corporations.",
     *     description="Needs role: app-groups.<br>
     *                  Returns only groups that have been added to the app as well.
     *                  Skips corporations that are not found in the local database.",
     *     tags={"Application - Groups"},
     *     security={{"BearerAuth"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="EVE corporation IDs array.",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(type="array", @OA\Items(type="integer"))
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of corporations with groups but without alliance.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
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
    public function corpGroupsBulkV1(ServerRequestInterface $request): ResponseInterface
    {
        return $this->groupsBulkFor(self::TYPE_CORPORATION, $request);
    }

    /**
     * @OA\Get(
     *     path="/app/v1/alliance-groups/{aid}",
     *     deprecated=true,
     *     operationId="allianceGroupsV1",
     *     summary="Return groups of the alliance.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application - Groups"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="aid",
     *         in="path",
     *         required=true,
     *         description="EVE alliance ID.",
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
     *         description="Alliance not found. (default reason phrase)"
     *     )
     * )
     */
    public function allianceGroupsV1(string $aid, ServerRequestInterface $request): ResponseInterface
    {
        return $this->corpOrAllianceGroups($aid, self::TYPE_ALLIANCE, $request);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/app/v2/alliance-groups/{aid}",
     *     operationId="allianceGroupsV2",
     *     summary="Return groups of the alliance.",
     *     description="Needs role: app-groups.<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application - Groups"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="aid",
     *         in="path",
     *         required=true,
     *         description="EVE alliance ID.",
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
     *         description="Reason phrase: Alliance not found."
     *     )
     * )
     */
    public function allianceGroupsV2(string $aid, ServerRequestInterface $request): ResponseInterface
    {
        $this->response = $this->allianceGroupsV1($aid, $request);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Alliance not found.');
        }

        return $this->response;
    }

    /**
     * @noinspection PhpUnused
     * @OA\Post(
     *     path="/app/v1/alliance-groups",
     *     operationId="allianceGroupsBulkV1",
     *     summary="Return groups of multiple alliances.",
     *     description="Needs role: app-groups.<br>
     *                  Returns only groups that have been added to the app as well.
     *                  Skips alliances that are not found in the local database.",
     *     tags={"Application - Groups"},
     *     security={{"BearerAuth"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="EVE alliance IDs array.",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(type="array", @OA\Items(type="integer"))
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of alliances with groups.",
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
    public function allianceGroupsBulkV1(ServerRequestInterface $request): ResponseInterface
    {
        return $this->groupsBulkFor(self::TYPE_ALLIANCE, $request);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/app/v1/groups-with-fallback",
     *     operationId="groupsWithFallbackV1",
     *     summary="Returns groups from the character's account, if available, or the corporation and alliance.",
     *     description="Needs role: app-groups.<br>
     *                  Returns only groups that have been added to the app as well.<br>
     *                  It is not checked if character, corporation and alliance match.",
     *     tags={"Application - Groups"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="character",
     *         in="query",
     *         required=true,
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="corporation",
     *         in="query",
     *         required=true,
     *         description="EVE corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="alliance",
     *         in="query",
     *         description="EVE alliance ID.",
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
     *     )
     * )
     */
    public function groupsWithFallbackV1(ServerRequestInterface $request): ResponseInterface
    {
        $characterId = (int) $this->getQueryParam($request, 'character');
        $corporationId = (int) $this->getQueryParam($request, 'corporation');
        $allianceId = (int) $this->getQueryParam($request, 'alliance');

        $appGroups = $this->getAppGroups($request);

        $characterResult = $this->getGroupsForPlayer($characterId, $appGroups);
        if ($characterResult !== null) {
            // could be an empty result
            return $this->withJson($characterResult[self::KEY_GROUPS]);
        }

        $fallbackGroups = [];

        $corporationResult = $this->getGroupsFor(self::TYPE_CORPORATION, $corporationId, $appGroups);
        if ($corporationResult !== null) {
            $fallbackGroups = $corporationResult[self::KEY_GROUPS];
        }

        $allianceResult = $this->getGroupsFor(self::TYPE_ALLIANCE, $allianceId, $appGroups);
        if ($allianceResult !== null) {
            // add groups that are not already in the result set
            foreach ($allianceResult[self::KEY_GROUPS] as $allianceGroup) {
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

        return $this->withJson($fallbackGroups);
    }

    private function corpOrAllianceGroups(string $id, string $type, ServerRequestInterface $request): ResponseInterface
    {
        $appGroups = $this->getAppGroups($request);
        $result = $this->getGroupsFor($type, (int) $id, $appGroups);

        if ($result === null) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($result[self::KEY_GROUPS]);
    }

    /**
     * @param string $type "Player", "Corporation" or "Alliance"
     * @return ResponseInterface
     */
    private function groupsBulkFor(string $type, ServerRequestInterface $request): ResponseInterface
    {
        $ids = $this->getIntegerArrayFromBody($request);
        if ($ids === null) {
            return $this->response->withStatus(400);
        }
        if (count($ids) === 0) {
            return $this->withJson([]);
        }

        $appGroups = $this->getAppGroups($request);

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

        return $this->withJson($result);
    }

    /**
     * @param int $characterId
     * @param Group[] $appGroups
     * @return null|array Returns NULL if character was not found.
     */
    private function getGroupsForPlayer(int $characterId, array $appGroups): ?array
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
            self::KEY_GROUPS => []
        ];

        if ($this->accountService->groupsDeactivated($char->getPlayer())) {
            return $result;
        }

        foreach ($appGroups as $appGroup) {
            foreach ($char->getPlayer()->getGroups() as $playerGroup) {
                if ($appGroup->getId() === $playerGroup->getId()) {
                    $result[self::KEY_GROUPS][] = $playerGroup;
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
     * @param Group[] $appGroups
     * @return null|array Returns NULL if corporation was not found.
     * @see \Neucore\Entity\Corporation::jsonSerialize()
     * @see \Neucore\Entity\Alliance::jsonSerialize()
     * @see \Neucore\Entity\Group::jsonSerialize()
     */
    private function getGroupsFor(string $entityName, int $entityId, array $appGroups): ?array
    {
        $repository = $entityName === self::TYPE_CORPORATION ?
            $this->repositoryFactory->getCorporationRepository() :
            $this->repositoryFactory->getAllianceRepository();

        $entity = $repository->find($entityId);
        if ($entity === null) {
            return null;
        }

        $result = $entity->jsonSerialize();
        if (array_key_exists(self::KEY_ALLIANCE, $result)) {
            unset($result[self::KEY_ALLIANCE]);
        }
        $result[self::KEY_GROUPS] = [];

        foreach ($appGroups as $appGroup) {
            foreach ($entity->getGroups() as $corpGroup) {
                if ($appGroup->getId() === $corpGroup->getId()) {
                    $result[self::KEY_GROUPS][] = $corpGroup->jsonSerialize();
                }
            }
        }

        return $result;
    }

    /**
     * @param ServerRequestInterface $request
     * @return Group[]
     */
    private function getAppGroups(ServerRequestInterface $request): array
    {
        $app = $this->appAuthService->getApp($request);

        return $app ? $app->getGroups() : [];
    }
}
