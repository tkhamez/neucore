<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Repository\CharacterRepository;
use Brave\Core\Roles;
use Brave\Core\Service\AutoGroupAssignment;
use Brave\Core\Service\CoreCharacter;
use Brave\Core\Service\EsiCharacter;
use Brave\Core\Service\UserAuth;
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
     * @var UserAuth
     */
    private $uas;

    /**
     * @var EsiCharacter
     */
    private $esiCharService;

    /**
     * @var CoreCharacter
     */
    private $coreCharService;

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    public function __construct(Response $response, UserAuth $uas, EsiCharacter $esiCs,
        CoreCharacter $coreCs, CharacterRepository $charRepo)
    {
        $this->res = $response;
        $this->uas = $uas;
        $this->esiCharService = $esiCs;
        $this->coreCharService = $coreCs;
        $this->charRepo = $charRepo;
    }

    /**
     * @SWG\Get(
     *     path="/user/character/show",
     *     operationId="show",
     *     summary="Return the logged in EVE character.",
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
     * @SWG\Get(
     *     path="/user/character/find-by/{name}",
     *     operationId="findBy",
     *     summary="Return a list of characters that matches the name (partial matching).",
     *     description="Needs role: user-admin or group-manager",
     *     tags={"Character"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the character.",
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of characters (ID and name only).",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Character"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized"
     *     )
     * )
     */
    public function findBy(string $name): Response
    {
        $result = $this->charRepo->findByNamePartialMatch($name);

        $retVal = [];
        foreach ($result as $char) {
            $retVal[] = [
                'id' => $char->getId(),
                'name' => $char->getName(),
            ];
        }

        return $this->res->withJson($retVal);
    }

    /**
     * @SWG\Get(
     *     path="/user/character/find-player-of/{id}",
     *     operationId="findPlayerOf",
     *     summary="Return the player to whom the character belongs.",
     *     description="Needs role: user-admin or group-manager",
     *     tags={"Character"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the character.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The player with id and name properties only.",
     *         @SWG\Schema(ref="#/definitions/Player")
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="No player found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized"
     *     )
     * )
     */
    public function findPlayerOf(string $id): Response
    {
        $char = $this->charRepo->find((int) $id);

        if ($char === null) {
            return $this->res->withStatus(204);
        }

        return $this->res->withJson([
            'id' => $char->getPlayer()->getId(),
            'name' => $char->getPlayer()->getName(),
        ]);
    }

    /**
     * @SWG\Put(
     *     path="/user/character/{id}/update",
     *     operationId="update",
     *     summary="Update a character with data from ESI.",
     *     description="Needs role: user or user-admin to update any character.
     *                  It also updates groups and verifies the OAuth token.",
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
    public function update(string $id, AutoGroupAssignment $groupAssign): Response
    {
        // find character
        $char = null;
        $player = $this->uas->getUser()->getPlayer();
        if ($player->hasRole(Roles::USER_ADMIN)) {
            $char = $this->charRepo->find((int) $id);
        } else {
            foreach ($this->uas->getUser()->getPlayer()->getCharacters() as $c) {
                if ($c->getId() === (int) $id) {
                    $char = $c;
                    break;
                }
            }
        }

        if ($char === null) {
            return $this->res->withStatus(404);
        }

        // update from ESI
        $updatedChar = $this->esiCharService->fetchCharacterWithCorporationAndAlliance($char->getId());
        if ($updatedChar === null) {
            return $this->res->withStatus(503);
        }

        // check token
        if ((string) $updatedChar->getRefreshToken() !== '') {
            $this->coreCharService->checkTokenUpdateCharacter($updatedChar);
        }

        // assign auto groups
        $groupAssign->assign($updatedChar->getPlayer()->getId());

        return $this->res->withJson($updatedChar);
    }
}
