<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Service\CharacterService;
use Brave\Core\Service\EsiService;
use Brave\Core\Service\UserAuthService;
use Slim\Http\Response;

/**
 * @SWG\Tag(
 *     name="Character",
 *     description="Character related functions."
 * )
 */
class CharacterController
{
    /**
     * @var Response
     */
    private $res;

    /**
     * @var UserAuthService
     */
    private $uas;

    /**
     * @var EsiService
     */
    private $es;

    /**
     * @var CharacterService
     */
    private $charService;

    public function __construct(Response $response, UserAuthService $uas, CharacterService $cs)
    {
        $this->res = $response;
        $this->uas = $uas;
        $this->charService = $cs;
    }

    /**
     * @SWG\Get(
     *     path="/user/character/show",
     *     operationId="show",
     *     summary="Returns the logged in EVE character.",
     *     description="Needs role: user",
     *     tags={"Character"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The logged in EVE character.",
     *         @SWG\Schema(ref="#/definitions/Character")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized"
     *     )
     * )
     */
    public function show(): Response
    {
        return $this->res->withJson($this->uas->getUser());
    }

    /**
     * @SWG\Put(
     *     path="/user/character/update/{id}",
     *     operationId="update",
     *     summary="Updates a character of the logged in player account with data from ESI.",
     *     description="Needs role: user",
     *     tags={"Character"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The updated character.",
     *         @SWG\Schema(ref="#/definitions/Character")
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Character not found on this account."
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
    public function update(string $id): Response
    {
        $char = null;
        foreach ($this->uas->getUser()->getPlayer()->getCharacters() as $c) {
            if ($c->getId() === (int) $id) {
                $char = $c;
                break;
            }
        }

        if ($char === null) {
            return $this->res->withStatus(404);
        }

        $updatedChar = $this->charService->fetchCharacter($char->getId(), true);
        if ($updatedChar === null) {
            return $this->res->withStatus(503);
        }

        return $this->res->withJson($updatedChar);
    }
}
