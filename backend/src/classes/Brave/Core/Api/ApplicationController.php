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
     *     summary="Groups to which the player belongs. Any character ID of the player account can be used.",
     *     description="Needs role: app",
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
        $char = $this->charRepo->find((int) $cid);

        if ($char === null) {
            return $this->response->withStatus(404);
        }

        $playerGroups = $char->getPlayer()->getGroups();
        $appGroups = $this->appService->getApp($request)->getGroups();

        $result = [];
        foreach ($appGroups as $appGroup) {
            foreach ($playerGroups as $playerGroup) {
                if ($appGroup->getId() === $playerGroup->getId()) {
                    $result[] = $playerGroup;
                }
            }
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
}
