<?php

declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Controller\BaseController;
use Neucore\Entity\Character;
use Neucore\Entity\Player;
use OpenApi\Annotations as OAT;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


#[OA\Tag(name: 'Application - Characters')]
#[OA\Schema(
    schema: 'PlayerWithCharcterId',
    required: ['id', 'name', 'characterId'],
    properties: [
        new OA\Property(property: 'id', type: 'int'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'characterId', type: 'int')
    ]
)]
class CharController extends BaseController
{
    public const ERROR_CHARACTER_NOT_FOUND = 'Character not found.';

    #[OA\Get(
        path: '/app/v1/main/{cid}',
        operationId: 'mainV1',
        description: 'Needs role: app-chars.<br>It is possible that an account has no main character.',
        summary: 'Returns the main character of the player account to which the character ID belongs.',
        security: [['BearerAuth' => []]],
        tags: ['Application - Characters'],
        parameters: [
            new OA\Parameter(
                name: 'cid',
                description: 'EVE character ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The main character',
                content: new OA\JsonContent(ref: '#/components/schemas/Character')
            ),
            new OA\Response(response: '204', description: 'No main character found.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Character not found. (default reason phrase)'),
            new OA\Response(
                response: '500',
                description: '',
                content: new OA\JsonContent(type: 'string')
            )
        ],
        deprecated: true,
    )]
    public function mainV1(string $cid): ResponseInterface
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int)$cid);
        if ($char === null) {
            return $this->response->withStatus(404);
        }

        $main = $char->getPlayer()->getMain();
        if ($main === null) {
            return $this->response->withStatus(204);
        }

        return $this->withJson($main);
    }

    #[OA\Get(
        path: '/app/v2/main/{cid}',
        operationId: 'mainV2',
        description: 'Needs role: app-chars.<br>It is possible that an account has no main character.',
        summary: 'Returns the main character of the player account to which the character ID belongs.',
        security: [['BearerAuth' => []]],
        tags: ['Application - Characters'],
        parameters: [
            new OA\Parameter(
                name: 'cid',
                description: 'EVE character ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The main character',
                content: new OA\JsonContent(ref: '#/components/schemas/Character')
            ),
            new OA\Response(response: '204', description: 'No main character found.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Reason phrase: Character not found.'),
            new OA\Response(
                response: '500',
                description: '',
                content: new OA\JsonContent(type: 'string')
            )
        ],
    )]
    public function mainV2(string $cid): ResponseInterface
    {
        $this->response = $this->mainV1($cid);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, self::ERROR_CHARACTER_NOT_FOUND);
        }

        return $this->response;
    }

    #[OA\Get(
        path: '/app/v1/player/{characterId}',
        operationId: 'playerV1',
        description: 'Needs role: app-chars.',
        summary: 'Returns the player account to which the character ID belongs.',
        security: [['BearerAuth' => []]],
        tags: ['Application - Characters'],
        parameters: [
            new OA\Parameter(
                name: 'characterId',
                description: 'EVE character ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The player, only id and name properties are returned.',
                content: new OA\JsonContent(ref: '#/components/schemas/Player')
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Character not found.'),
            new OA\Response(response: '500', description: '',
                content: new OA\JsonContent(type: 'string')
            )
        ],
    )]
    public function playerV1(string $characterId): ResponseInterface
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find((int)$characterId);
        if ($character === null) {
            return $this->response->withStatus(404, self::ERROR_CHARACTER_NOT_FOUND);
        }

        return $this->withJson($character->getPlayer()->jsonSerialize(true));
    }

    #[OA\Post(
        path: '/app/v1/players',
        operationId: 'playersV1',
        description: 'Needs role: app-chars.',
        summary: 'Returns player accounts identified by character IDs. Can contain the same player several times.',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: 'EVE character IDs array.',
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))
            )
        ),
        tags: ['Application - Characters'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'One entry for each character ID that was provided and found.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/PlayerWithCharcterId')
                )
            ),
            new OA\Response(response: '400', description: 'Invalid body.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '500', description: '',
                content: new OA\JsonContent(type: 'string')
            )
        ],
    )]
    public function playersV1(ServerRequestInterface $request): ResponseInterface
    {
        $ids = $this->getIntegerArrayFromBody($request);
        if ($ids === null) {
            return $this->response->withStatus(400);
        }

        $players = $this->repositoryFactory->getCharacterRepository()->findPlayersByCharacters($ids);

        return $this->withJson($players);
    }

    #[OA\Get(
        path: '/app/v1/characters/{characterId}',
        operationId: 'charactersV1',
        description: 'Needs role: app-chars.',
        summary: 'Returns all characters of the player account to which the character ID belongs.',
        security: [['BearerAuth' => []]],
        tags: ['Application - Characters'],
        parameters: [
            new OA\Parameter(
                name: 'characterId',
                description: 'EVE character ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'All characters from the player account.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Character')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Character not found.'),
            new OA\Response(response: '500', description: '',
                content: new OA\JsonContent(type: 'string')
            )
        ],
    )]
    public function charactersV1(string $characterId): ResponseInterface
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int)$characterId);
        if ($char === null) {
            return $this->response->withStatus(404, self::ERROR_CHARACTER_NOT_FOUND);
        }

        return $this->withJson($char->getPlayer()->getCharacters());
    }

    #[OA\Post(
        path: '/app/v1/characters',
        operationId: 'charactersBulkV1',
        description: 'Needs role: app-chars.',
        summary: 'Returns all characters from multiple player accounts identified by character IDs.',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: 'EVE character IDs array.',
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))
            )
        ),
        tags: ['Application - Characters'],
        responses: [
            new OA\Response(response: '400', description: 'Invalid body.'),
            new OA\Response(
                response: '200',
                description: 'All characters from the player account.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'array', items: new OA\Items(type: 'integer'))
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(
                response: '500',
                description: '',
                content: new OA\JsonContent(type: 'string')
            )
        ],
    )]
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
            $result[$character['playerId']][] = (int)$character['id'];
        }

        return $this->withJson(array_values($result));
    }

    #[OA\Post(
        path: '/app/v1/character-list',
        operationId: 'characterListV1',
        description: 'Needs role: app-chars.',
        summary: 'Returns all known characters from the parameter list.',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: 'Array with EVE character IDs.',
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'integer'))
            )
        ),
        tags: ['Application - Characters'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'A list of characters (does not include the corporation property).',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Character')
                )
            ),
            new OA\Response(response: '400', description: 'Invalid body.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(
                response: '500',
                description: '',
                content: new OA\JsonContent(type: 'string')
            )
        ],
    )]
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

    #[OA\Get(
        path: '/app/v1/player-chars/{playerId}',
        operationId: 'playerCharactersV1',
        description: 'Needs role: app-chars.',
        summary: 'Returns all characters from the player account.',
        security: [['BearerAuth' => []]],
        tags: ['Application - Characters'],
        parameters: [
            new OA\Parameter(
                name: 'playerId',
                description: 'Player ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'All characters from the player account.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Character')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Player not found.'),
            new OA\Response(response: '500', description: '', content: new OA\JsonContent(type: 'string'))
        ],
    )]
    public function playerCharactersV1(string $playerId): ResponseInterface
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find((int)$playerId);
        if ($player === null) {
            return $this->response->withStatus(404, 'Player not found.');
        }

        return $this->withJson($player->getCharacters());
    }

    #[OA\Get(
        path: '/app/v1/player-with-characters/{characterId}',
        operationId: 'playerWithCharactersV1',
        description: 'Needs role: app-chars.',
        summary: 'Returns the player account to which the character ID belongs with all characters.',
        security: [['BearerAuth' => []]],
        tags: ['Application - Characters'],
        parameters: [
            new OA\Parameter(
                name: 'characterId',
                description: 'EVE character ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The player, only id, name and characters properties are returned.',
                content: new OA\JsonContent(ref: '#/components/schemas/Player')
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Character not found.'),
            new OA\Response(response: '500', description: '', content: new OA\JsonContent(type: 'string'))
        ],
    )]
    public function playerWithCharactersV1(string $characterId): ResponseInterface
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find((int)$characterId);
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
     * @OAT\Get(
     *     path="/app/v1/removed-characters/{characterId}",
     *     operationId="removedCharactersV1",
     *     summary="Returns all characters that were removed from the player account to which the character ID belongs.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OAT\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         @OAT\Schema(type="integer")
     *     ),
     *     @OAT\Response(
     *         response="200",
     *         description="All removed characters from the player account.",
     *         @OAT\JsonContent(type="array", @OAT\Items(ref="#/components/schemas/RemovedCharacter"))
     *     ),
     *     @OAT\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OAT\Response(
     *         response="404",
     *         description="Character not found."
     *     ),
     *     @OAT\Response(
     *         response="500",
     *         description="",
     *         @OAT\JsonContent(type="string")
     *     )
     * )
     */
    public function removedCharactersV1(string $characterId): ResponseInterface
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int)$characterId);
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
     * @OAT\Get(
     *     path="/app/v1/incoming-characters/{characterId}",
     *     operationId="incomingCharactersV1",
     *     summary="Returns all characters that were moved from another account to the player account to which the ID belongs.",
     *     description="Needs role: app-chars.",
     *     tags={"Application - Characters"},
     *     security={{"BearerAuth"={}}},
     *     @OAT\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         @OAT\Schema(type="integer")
     *     ),
     *     @OAT\Response(
     *         response="200",
     *         description="All incoming characters from the player account.",
     *         @OAT\JsonContent(type="array", @OAT\Items(ref="#/components/schemas/RemovedCharacter"))
     *     ),
     *     @OAT\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OAT\Response(
     *         response="404",
     *         description="Character not found."
     *     ),
     *     @OAT\Response(
     *         response="500",
     *         description="",
     *         @OAT\JsonContent(type="string")
     *     )
     * )
     */
    public function incomingCharactersV1(string $characterId): ResponseInterface
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int)$characterId);
        if ($char === null) {
            return $this->response->withStatus(404, self::ERROR_CHARACTER_NOT_FOUND);
        }

        $result = [];
        foreach ($char->getPlayer()->getIncomingCharacters() as $character) {
            $result[] = $character;
        }

        return $this->withJson($result);
    }

    #[OA\Get(
        path: '/app/v1/corp-players/{corporationId}',
        operationId: 'corporationPlayersV1',
        description: 'Needs role: app-chars.',
        summary: 'Returns a list of all players that have a character in the corporation.',
        security: [['BearerAuth' => []]],
        tags: ['Application - Characters'],
        parameters: [
            new OA\Parameter(
                name: 'corporationId',
                description: 'EVE corporation ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of players, only id and name properties are returned.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Player')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '500', description: '', content: new OA\JsonContent(type: 'string'))
        ],
    )]
    public function corporationPlayersV1(string $corporationId): ResponseInterface
    {
        $players = $this->repositoryFactory->getPlayerRepository()->findInCorporation((int)$corporationId);

        return $this->withJson(array_map(function (Player $player) {
            return $player->jsonSerialize(true);
        }, $players));
    }

    #[OA\Get(
        path: '/app/v1/corp-characters/{corporationId}',
        operationId: 'corporationCharactersV1',
        description: 'Needs role: app-chars.',
        summary: 'Returns a list of all known characters from the corporation.',
        security: [['BearerAuth' => []]],
        tags: ['Application - Characters'],
        parameters: [
            new OA\Parameter(
                name: 'corporationId',
                description: 'EVE corporation ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of characters (does not include the corporation property).',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Character')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '500', description: '', content: new OA\JsonContent(type: 'string'))
        ],
    )]
    public function corporationCharactersV1(string $corporationId): ResponseInterface
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find((int)$corporationId);
        if ($corporation === null) {
            return $this->withJson([]);
        }

        return $this->withJson(array_map(function (Character $character) {
            return $character->jsonSerialize(false, false);
        }, $corporation->getCharacters()));
    }
}
