<?php declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\AutoGroupAssignment;
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
     * @var Account
     */
    private $charService;

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
        Account $charService,
        OAuthToken $tokenService
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);
        
        $this->userAuth = $userAuth;
        $this->esiData = $esiData;
        $this->charService = $charService;
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
        return $this->withJson($this->userAuth->getUser());
    }

    /**
     * @OA\Get(
     *     path="/user/character/find-by/{name}",
     *     operationId="findBy",
     *     summary="Return a list of characters that matches the name (partial matching).",
     *     description="Needs role: user-admin or group-manager",
     *     tags={"Character"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the character.",
     *         @OA\Schema(type="string")
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
     */
    public function findBy(string $name): ResponseInterface
    {
        $result = $this->repositoryFactory->getCharacterRepository()->findByNamePartialMatch($name);

        $retVal = [];
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
     *     description="Needs role: user or user-admin to update any character.
     *                  It also updates groups and verifies the OAuth token.",
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
    public function update(string $id, AutoGroupAssignment $groupAssign): ResponseInterface
    {
        // get player account
        $player = $this->userAuth->getUser()->getPlayer();

        // find character
        $char = null;
        if ($player->hasRole(Role::USER_ADMIN)) {
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
        $result = $this->charService->checkCharacter($updatedChar, $this->tokenService);
        if ($result === Account::CHECK_CHAR_DELETED) {
            $updatedChar = null;
        }

        if ($updatedChar !== null) {
            // assign auto groups
            $groupAssign->assign($updatedChar->getPlayer()->getId());
            $groupAssign->checkRequiredGroups($updatedChar->getPlayer()->getId());

            return $this->withJson($updatedChar);
        } else {
            return $this->response->withStatus(204);
        }
    }
}
