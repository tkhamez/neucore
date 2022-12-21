<?php

/** @noinspection PhpUnusedAliasInspection */

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Role;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Account;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use Neucore\Service\ObjectManager;
use Neucore\Service\UserAuth;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\ApiException;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdOk;

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
    private UserAuth $userAuth;

    private EsiData $esiData;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        UserAuth $userAuth,
        EsiData $esiData
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->userAuth = $userAuth;
        $this->esiData = $esiData;
    }

    /**
     * @OA\Get(
     *     path="/user/character/show",
     *     operationId="userCharacterShow",
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
     *     @OA\Parameter(
     *         name="currentOnly",
     *         in="query",
     *         description="Do not include old character names or moved characters. Defaults to false.",
     *         @OA\Schema(type="string", enum={"true", "false"})
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
    public function findCharacter(string $name, ServerRequestInterface $request): ResponseInterface
    {
        $name = trim($name);
        if (mb_strlen($name) < 3) {
            return $this->withJson([]);
        }

        $currentOnly = $this->getQueryParam($request, 'currentOnly') === 'true';
        $retVal = $this->repositoryFactory->getPlayerRepository()->findCharacters($name, $currentOnly);

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
        $name = trim($name);
        if (mb_strlen($name) < 3) {
            return $this->withJson([]);
        }

        $retVal = [];
        $result = $this->repositoryFactory->getCharacterRepository()->findMainByNamePartialMatch($name);
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
     * @OA\Put(
     *     path="/user/character/{id}/update",
     *     operationId="update",
     *     summary="Update a character with data from ESI.",
     *     description="Needs role: user to update own characters or user-admin, user-manager, group-admin, app-admin,
                        user-chars, tracking or watchlist to update any character. It also updates groups and
                        verifies the OAuth token.",
     *     tags={"Character"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The character was updated.",
     *         @OA\JsonContent(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="The character was deleted because the owner hash changed."
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
        // Note that user with the role tracking or watchlist should only update characters from
        // account from the respective lists, but there's no harm allowing them to update all as long as
        // this does not return any data, otherwise this would need to check permissions like it's done
        // for /user/player/{id}/characters.

        // get player account
        $player = $this->getUser($this->userAuth)->getPlayer();

        // find character
        $char = null;
        if (
            $player->hasRole(Role::USER_ADMIN) ||
            $player->hasRole(Role::USER_MANAGER) ||
            $player->hasRole(Role::GROUP_ADMIN) ||
            $player->hasRole(Role::APP_ADMIN) ||
            $player->hasRole(Role::USER_CHARS) ||
            $player->hasRole(Role::TRACKING) ||
            $player->hasRole(Role::WATCHLIST)
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

        $updatePlayerId = $char->getPlayer()->getId();

        // update from ESI
        $updatedChar = $this->esiData->fetchCharacterWithCorporationAndAlliance($char->getId());
        if ($updatedChar === null) {
            return $this->response->withStatus(503);
        }

        // check token and character owner hash - this may delete the character!
        $result = $accountService->checkCharacter($updatedChar);
        if ($result === Account::CHECK_CHAR_DELETED) {
            $updatedChar = null;

            // Cannot call flush a second time if a character with a name change was deleted (A new entity was
            // found ...), clearing the entity manager fixes this. Run CharacterControllerTest::testUpdate204
            // to reproduce with the next line is commented out.
            $this->objectManager->clear();
        }

        $accountService->updateGroups($updatePlayerId); // flushes the entity manager

        if ($updatedChar !== null) {
            // Do not return account data because roles tracking and watchlist may execute this.
            return $this->withJson(1); // Status 200
        } else {
            return $this->response->withStatus(204);
        }
    }

    /**
     * @OA\Post(
     *     path="/user/character/add/{id}",
     *     operationId="userCharacterAdd",
     *     summary="Add an EVE character to the database on a new account.",
     *     description="Needs role: user-admin",
     *     tags={"Character"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="Successfully created account with character."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Character not found."
     *     ),
     *     @OA\Response(
     *         response="409",
     *         description="Character already exists in local database."
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Role not found or ESI error."
     *     )
     * )
     */
    public function add(
        string $id,
        Account $accountService,
        EsiApiFactory $esiApiFactory,
        Config $config,
        LoggerInterface $log
    ): ResponseInterface {
        $charId = (int) $id;

        $userRole = $this->repositoryFactory->getRoleRepository()->findBy(['name' => Role::USER]);
        if (count($userRole) !== 1) {
            return $this->withJson('Could not find user role.', 500);
        }

        $characterCheck = $this->repositoryFactory->getCharacterRepository()->find($charId);
        if ($characterCheck) {
            return $this->withJson('Character already exists.', 409);
        }

        // Check if EVE character exists
        try {
            $eveChar = $esiApiFactory
                ->getCharacterApi()
                ->getCharactersCharacterId($charId, $config['eve']['datasource']);
        } catch (ApiException $e) {
            $body = $e->getResponseBody();
            if ($e->getCode() === 404 && is_string($body) && str_contains($body, 'Character not found')) {
                return $this->withJson('Character not found.', 404);
            } else {
                $log->error($e->getMessage());
                return $this->withJson('ESI error.', 500);
            }
        } catch (\Exception $e) { // InvalidArgumentException
            $log->error($e->getMessage());
            return $this->withJson('ESI error.', 500);
        }

        // Create new account
        if ($eveChar instanceof GetCharactersCharacterIdOk) {
            $char = $accountService->createNewPlayerWithMain($charId, $eveChar->getName());
            $char->getPlayer()->addRole($userRole[0]);
            $this->objectManager->persist($char->getPlayer());
            $this->objectManager->persist($char);
        }

        return $this->flushAndReturn(201);
    }
}
