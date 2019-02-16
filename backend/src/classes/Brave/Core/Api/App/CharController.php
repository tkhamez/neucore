<?php declare(strict_types=1);

namespace Brave\Core\Api\App;

use Brave\Core\Factory\RepositoryFactory;
use Slim\Http\Response;

class CharController
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    public function __construct(
        Response $response,
        RepositoryFactory $repositoryFactory
    ) {
        $this->response = $response;
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/main/{cid}",
     *     operationId="mainV1",
     *     summary="Returns the main character of the player account to which the character ID belongs.",
     *     description="Needs role: app-chars.<br>It is possible that an account has no main character.",
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
     *         description="Character (or player) not found. (default reason phrase)"
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
        if ($char === null || $char->getPlayer() === null) {
            return $this->response->withStatus(404);
        }

        $main = $char->getPlayer()->getMain();
        if ($main === null) {
            return $this->response->withStatus(204);
        }

        return $this->response->withJson($main);
    }

    /**
     * @SWG\Get(
     *     path="/app/v2/main/{cid}",
     *     operationId="mainV2",
     *     summary="Return the main character of the player account to which the character ID belongs.",
     *     description="Needs role: app-chars.<br>It is possible that an account has no main character.",
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
     *         description="Reason phrase: Character not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function mainV2(string $cid): Response
    {
        $this->response = $this->mainV1($cid);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Character not found.');
        }

        return $this->response;
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/characters/{characterId}",
     *     operationId="charactersV1",
     *     summary="Return all characters of the player account to which the character ID belongs.",
     *     description="Needs role: app-chars.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="All characters from the player account.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Character"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Character (or player) not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function charactersV1(string $characterId): Response
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int) $characterId);

        if ($char === null || $char->getPlayer() === null) {
            return $this->response->withStatus(404, 'Character not found.');
        }

        $result = [];
        foreach ($char->getPlayer()->getCharacters() as $character) {
            $result[] = $character;
        }

        return $this->response->withJson($result);
    }
}
