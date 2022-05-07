<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Controller\BaseController;
use Neucore\Entity\Character;
use Neucore\Entity\Player;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="Application - Characters"
 * )
 */
class CharController extends BaseController
{
    public const ERROR_CHARACTER_NOT_FOUND = 'Character not found.';

    /**
     * @OA\Get(
     *     path="/app/v1/main/{cid}",
     *     deprecated=true,
     *     operationId="mainV1",
     *     summary="Returns the main character of the player account to which the character ID belongs.",
     *     description="Needs role: app-chars.<br>It is possible that an account has no main character.",
     *     tags={"Application - Characters"},
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
     *         description="The main character",
     *         @OA\JsonContent(ref="#/components/schemas/Character")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="No main character found."
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
    public function mainV1(string $cid): ResponseInterface
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int) $cid);
        if ($char === null) {
            return $this->response->withStatus(404);
        }

        $main = $char->getPlayer()->getMain();
        if ($main === null) {
            return $this->response->withStatus(204);
        }

        return $this->withJson($main);
    }

    /**
     * @OA\Get(
     *     path="/app/v2/main/{cid}",
     *     operationId="mainV2",
     *     summary="Returns the main character of the player account to which the character ID belongs.",
     *     description="Needs role: app-chars.<br>It is possible that an account has no main character.",
     *     tags={"Application - Characters"},
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
     *         description="The main character",
     *         @OA\JsonContent(ref="#/components/schemas/Character")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="No main character found."
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
    public function mainV2(string $cid): ResponseInterface
    {
        $this->response = $this->mainV1($cid);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, self::ERROR_CHARACTER_NOT_FOUND);
        }

        return $this->response;
    }

    /**
     * @OA\Get(
     *     path="/app/v1/player/{characterId}",
     *     operationId="playerV1",
     *     summary="Returns the player account to which the character ID belongs.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The player, only id and name properties are returned.",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Character not found."
     *     )
     * )
     */
    public function playerV1(string $characterId): ResponseInterface
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find((int) $characterId);
        if ($character === null) {
            return $this->response->withStatus(404, self::ERROR_CHARACTER_NOT_FOUND);
        }

        return $this->withJson($character->getPlayer()->jsonSerialize(true));
    }

    /**
     * @OA\Get(
     *     path="/app/v1/characters/{characterId}",
     *     operationId="charactersV1",
     *     summary="Returns all characters of the player account to which the character ID belongs.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="All characters from the player account.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Character"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Character not found."
     *     )
     * )
     */
    public function charactersV1(string $characterId): ResponseInterface
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int) $characterId);
        if ($char === null) {
            return $this->response->withStatus(404, self::ERROR_CHARACTER_NOT_FOUND);
        }

        return $this->withJson($char->getPlayer()->getCharacters());
    }

    /**
     * @OA\Post(
     *     path="/app/v1/characters",
     *     operationId="charactersBulkV1",
     *     summary="Returns all characters from multiple player accounts identified by character IDs.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
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
     *         response="400",
     *         description="Invalid body."
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="All characters from the player account.",
     *         @OA\JsonContent(type="array", @OA\Items(type="array", @OA\Items(type="integer")))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function charactersBulk(ServerRequestInterface $request): ResponseInterface
    {
        $ids = $this->getIntegerArrayFromBody($request);
        if ($ids === null) {
            return $this->response->withStatus(400);
        }

        $playerIds = $this->repositoryFactory->getPlayerRepository()->findPlayersOfCharacters($ids);
        $characters = $this->repositoryFactory->getCharacterRepository()->getAllCharactersFromPlayers($playerIds);

        $result = [];
        foreach ($characters as $character) {
            $result[$character['playerId']][] = (int) $character['id'];
        }

        return $this->withJson(array_values($result));
    }

    /**
     * @OA\Post(
     *     path="/app/v1/character-list",
     *     operationId="characterListV1",
     *     summary="Returns all known characters from the parameter list.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Array with EVE character IDs.",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="A list of characters (does not include the corporation property).",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Character"))
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
    public function characterListV1(ServerRequestInterface $request): ResponseInterface
    {
        $ids = $this->getIntegerArrayFromBody($request);
        if ($ids === null) {
            return $this->response->withStatus(400);
        }

        if (empty($ids)) {
            return $this->withJson([]);
        }

        $characters = $this->repositoryFactory->getCharacterRepository()->findBy(['id' => $ids]);

        return $this->withJson(array_map(function (Character $character) {
            return $character->jsonSerialize(false, false);
        }, $characters));
    }

    /**
     * @OA\Get(
     *     path="/app/v1/player-chars/{playerId}",
     *     operationId="playerCharactersV1",
     *     summary="Returns all characters from the player account.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="playerId",
     *         in="path",
     *         required=true,
     *         description="Player ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="All characters from the player account.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Character"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Player not found."
     *     )
     * )
     */
    public function playerCharactersV1(string $playerId): ResponseInterface
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $playerId);
        if ($player === null) {
            return $this->response->withStatus(404, 'Player not found.');
        }

        return $this->withJson($player->getCharacters());
    }

    /**
     * @OA\Get(
     *     path="/app/v1/player-with-characters/{characterId}",
     *     operationId="playerWithCharactersV1",
     *     summary="Returns the player account to which the character ID belongs with all characters.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The player, only id, name and characters properties are returned.",
     *         @OA\JsonContent(ref="#/components/schemas/Player")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Character not found."
     *     )
     * )
     */
    public function playerWithCharactersV1(string $characterId): ResponseInterface
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find((int) $characterId);
        if ($character === null) {
            return $this->response->withStatus(404, CharController::ERROR_CHARACTER_NOT_FOUND);
        }

        $player = $character->getPlayer();

        return $this->withJson([
            'id' => $player->getId(),
            'name' => $player->getName(),
            'characters' => $player->getCharacters(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/app/v1/removed-characters/{characterId}",
     *     operationId="removedCharactersV1",
     *     summary="Returns all characters that were removed from the player account to which the character ID
                    belongs.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="All removed characters from the player account.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/RemovedCharacter"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Character not found."
     *     )
     * )
     */
    public function removedCharactersV1(string $characterId): ResponseInterface
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int) $characterId);
        if ($char === null) {
            return $this->response->withStatus(404, self::ERROR_CHARACTER_NOT_FOUND);
        }

        $result = [];
        foreach ($char->getPlayer()->getRemovedCharacters() as $character) {
            $result[] = $character;
        }

        return $this->withJson($result);
    }

    /**
     * @OA\Get(
     *     path="/app/v1/incoming-characters/{characterId}",
     *     operationId="incomingCharactersV1",
     *     summary="Returns all characters that were moved from another account to the player account to which the
                    ID belongs.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="All incoming characters from the player account.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/RemovedCharacter"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Character not found."
     *     )
     * )
     */
    public function incomingCharactersV1(string $characterId): ResponseInterface
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int) $characterId);
        if ($char === null) {
            return $this->response->withStatus(404, self::ERROR_CHARACTER_NOT_FOUND);
        }

        $result = [];
        foreach ($char->getPlayer()->getIncomingCharacters() as $character) {
            $result[] = $character;
        }

        return $this->withJson($result);
    }

    /**
     * @OA\Get(
     *     path="/app/v1/corp-players/{corporationId}",
     *     operationId="corporationPlayersV1",
     *     summary="Returns a list of all players that have a character in the corporation.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="corporationId",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of players, only id and name properties are returned.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corporationPlayersV1(string $corporationId): ResponseInterface
    {
        $players = $this->repositoryFactory->getPlayerRepository()->findInCorporation((int) $corporationId);

        return $this->withJson(array_map(function (Player $player) {
            return $player->jsonSerialize(true);
        }, $players));
    }

    /**
     * @OA\Get(
     *     path="/app/v1/corp-characters/{corporationId}",
     *     operationId="corporationCharactersV1",
     *     summary="Returns a list of all known characters from the corporation.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="corporationId",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of characters (does not include the corporation property).",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Character"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corporationCharactersV1(string $corporationId): ResponseInterface
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find((int) $corporationId);
        if ($corporation === null) {
            return $this->withJson([]);
        }

        return $this->withJson(array_map(function (Character $character) {
            return $character->jsonSerialize(false, false);
        }, $corporation->getCharacters()));
    }
}
