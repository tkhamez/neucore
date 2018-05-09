<?php declare(strict_types=1);

namespace Brave\Core\Api;

use Brave\Core\Entity\CharacterRepository;
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

    public function __construct(Response $response, AppAuth $aap, CharacterRepository $cr)
    {
        $this->response = $response;
        $this->appService = $aap;
        $this->charRepo = $cr;
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
     *     summary="Return the groups of multiple players, identified by one of their character IDs.",
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
        $charIds = $request->getParsedBody();

        if (! is_array($charIds)) {
            return $this->response->withStatus(400);
        }

        $charIds = array_map('intval', $charIds);
        $charIds = array_unique($charIds);
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

    /**
     *
     * @param int $characterId
     * @param array $appGroups
     * @return void|\Brave\Core\Entity\Group[] Returns NULL if character was not found.
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

        $playerGroups = $char->getPlayer()->getGroups();
        foreach ($appGroups as $appGroup) {
            foreach ($playerGroups as $playerGroup) {
                if ($appGroup->getId() === $playerGroup->getId()) {
                    $result['groups'][] = $playerGroup;
                }
            }
        }

        return $result;
    }
}
