<?php declare(strict_types=1);

namespace Brave\Core\Api;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\CorporationRepository;
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
    private $appService;

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var CorporationRepository
     */
    private $corpRepo;

    public function __construct(
        Response $response,
        AppAuth $aap,
        CharacterRepository $charRepo,
        CorporationRepository $corpRepo
    ) {
        $this->response = $response;
        $this->appService = $aap;
        $this->charRepo = $charRepo;
        $this->corpRepo = $corpRepo;
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
        return $this->response->withJson($this->appService->getApp($request));
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
        $appGroups = $this->appService->getApp($request)->getGroups();
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

        $appGroups = $this->appService->getApp($request)->getGroups();

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
     *     summary="Return groups for the corporation.",
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
        $appGroups = $this->appService->getApp($request)->getGroups();
        $result = $this->getGroupsForCorporation((int) $cid, $appGroups);

        if ($result === null) {
            return $this->response->withStatus(404);
        }

        return $this->response->withJson($result['groups']);
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/corp-groups",
     *     operationId="corpGroupsBulkV1",
     *     summary="Return groups for multiple corporations.",
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

        $appGroups = $this->appService->getApp($request)->getGroups();

        $result = [];
        foreach ($corpIds as $corpId) {
            if ($corpId <= 0) {
                continue;
            }

            $corpGroups = $this->getGroupsForCorporation($corpId, $appGroups);
            if ($corpGroups === null) {
                continue;
            }

            $result[] = $corpGroups;
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
    public function mainV1($cid): Response
    {
        $char = $this->charRepo->find((int) $cid);

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
     * @return void|array Returns NULL if character was not found.
     */
    private function getGroupsForPlayer(int $characterId, array $appGroups)
    {
        $char = $this->charRepo->find($characterId);
        if ($char === null) {
            return;
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
     * Get groups of corporation.
     *
     * Returns data from jsonSerialize() of a Corporation object plus
     * all of it's groups that also belongs to the app.
     *
     * @param int $corporationId
     * @param \Brave\Core\Entity\Group[] $appGroups
     * @return void|array Returns NULL if corporation was not found.
     * @see \Brave\Core\Entity\Corporation::jsonSerialize()
     * @see \Brave\Core\Entity\Group::jsonSerialize()
     */
    private function getGroupsForCorporation(int $corporationId, array $appGroups)
    {
        $corp = $this->corpRepo->find($corporationId);
        if ($corp === null) {
            return;
        }

        $result = $corp->jsonSerialize();
        unset($result['alliance']);
        $result['groups'] = [];

        foreach ($appGroups as $appGroup) {
            foreach ($corp->getGroups() as $corpGroup) {
                if ($appGroup->getId() === $corpGroup->getId()) {
                    $result['groups'][] = $corpGroup->jsonSerialize();
                }
            }
        }

        return $result;
    }
}
