<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Account;
use Neucore\Service\EsiData;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use Neucore\Service\UserAuth;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * @OA\Tag(
 *     name="Character",
 *     description="Character related functions."
 * )
 * @OA\Schema(
 *     schema="SearchResult",
 *     required={"character_id", "character_name", "player_id", "player_name"},
 *     @OA\Property(
 *         property="character_id",
 *         type="integer",
 *         format="int64"
 *     ),
 *     @OA\Property(
 *         property="character_name",
 *         type="string"
 *     ),
 *     @OA\Property(
 *         property="player_id",
 *         type="integer"
 *     ),
 *     @OA\Property(
 *         property="player_name",
 *         type="string"
 *     )
 * )
 */
class CharacterController extends BaseController
{
    /**
     * @var UserAuth
     */
    private $userAuth;

    /**
     * @var EsiData
     */
    private $esiData;

    /**
     * @var OAuthToken
     */
    private $tokenService;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        UserAuth $userAuth,
        EsiData $esiData,
        OAuthToken $tokenService
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);
        
        $this->userAuth = $userAuth;
        $this->esiData = $esiData;
        $this->tokenService = $tokenService;
    }

    /**
     * @OA\Get(
     *     path="/user/character/show",
     *     operationId="show",
     *     summary="Return the logged in EVE character.",
     *     description="Needs role: user",
     *     tags={"Character"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="The logged in EVE character.",
     *         @OA\JsonContent(ref="#/components/schemas/Character")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized"
     *     )
     * )
     */
    public function show(): ResponseInterface
    {
        return $this->withJson($this->getUser($this->userAuth));
    }

    /**
     * @OA\Get(
     *     path="/user/character/find-character/{name}",
     *     operationId="findCharacter",
     *     summary="Returns a list of characters (together with the name of the player account/main character)
                    that matches the name (partial matching).",
     *     description="Needs role: user-admin, user-manager, user-chars",
     *     tags={"Character"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the character (min. 3 characters).",
     *         @OA\Schema(type="string", minLength=3)
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of characters.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/SearchResult"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized"
     *     )
     * )
     * @noinspection PhpUnused
     */
    public function findCharacter(string $name, bool $mainOnly = false): ResponseInterface
    {
        $name = trim($name);
        if (mb_strlen($name) < 3) {
            return $this->withJson([]);
        }

        $retVal = [];
        $result = $this->repositoryFactory->getCharacterRepository()->findByNamePartialMatch($name, $mainOnly);
        foreach ($result as $char) {
            $retVal[] = [
                'character_id' => $char->getId(),
                'character_name' => $char->getName(),
                'player_id' => $char->getPlayer()->getId(),
                'player_name' => $char->getPlayer()->getName(),
            ];
        }

        return $this->withJson($retVal);
    }

    /**
     * @OA\Get(
     *     path="/user/character/find-player/{name}",
     *     operationId="findPlayer",
     *     summary="Return a list of players that matches the main character name (partial matching).",
     *     description="Needs role: group-manager",
     *     tags={"Character"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the main character (min. 3 characters).",
     *         @OA\Schema(type="string", minLength=3)
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of main characters.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/SearchResult"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized"
     *     )
     * )
     * @noinspection PhpUnused
     */
    public function findPlayer(string $name): ResponseInterface
    {
        return $this->findCharacter($name, true);
    }

    /**
     * @OA\Put(
     *     path="/user/character/{id}/update",
     *     operationId="update",
     *     summary="Update a character with data from ESI.",
     *     description="Needs role: user or user-admin, user-manager, group-admin, watchlist or tracking to
                        update any character. It also updates groups and verifies the OAuth token.",
     *     tags={"Character"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The updated character.",
     *         @OA\JsonContent(ref="#/components/schemas/Character")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="If the character was deleted because the owner hash changed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Character not found on this account."
     *     ),
     *     @OA\Response(
     *         response="503",
     *         description="ESI request failed."
     *     )
     * )
     */
    public function update(string $id, Account $accountService): ResponseInterface
    {
        // get player account
        $player = $this->getUser($this->userAuth)->getPlayer();

        // find character
        $char = null;
        if (
            $player->hasRole(Role::USER_ADMIN) ||
            $player->hasRole(Role::USER_MANAGER) ||
            $player->hasRole(Role::GROUP_ADMIN) ||
            $player->hasRole(Role::WATCHLIST) ||
            $player->hasRole(Role::TRACKING)
        ) {
            $char = $this->repositoryFactory->getCharacterRepository()->find((int) $id);
        } else {
            foreach ($player->getCharacters() as $c) {
                if ($c->getId() === (int) $id) {
                    $char = $c;
                    break;
                }
            }
        }

        if ($char === null) {
            return $this->response->withStatus(404);
        }

        // update from ESI
        $updatedChar = $this->esiData->fetchCharacterWithCorporationAndAlliance($char->getId());
        if ($updatedChar === null) {
            return $this->response->withStatus(503);
        }

        // check token and character owner hash - this may delete the character!
        $result = $accountService->checkCharacter($updatedChar, $this->tokenService);
        if ($result === Account::CHECK_CHAR_DELETED) {
            $updatedChar = null;
        }

        if ($updatedChar !== null) {
            $accountService->updateGroups($updatedChar->getPlayer()->getId()); // flushes the entity manager

            return $this->withJson($updatedChar);
        } else {
            return $this->response->withStatus(204);
        }
    }
}
