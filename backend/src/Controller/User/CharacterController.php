<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Data\SearchResult;
use Neucore\Entity\Character;
use Neucore\Entity\Role;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Exception;
use Neucore\Service\Account;
use Neucore\Service\EsiData;
use Neucore\Service\ObjectManager;
use Neucore\Service\PluginService;
use Neucore\Service\UserAuth;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[OA\Tag(name: 'Character', description: 'Character related functions.')]
class CharacterController extends BaseController
{
    private UserAuth $userAuth;

    private EsiData $esiData;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        UserAuth $userAuth,
        EsiData $esiData,
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->userAuth = $userAuth;
        $this->esiData = $esiData;
    }

    #[OA\Get(
        path: '/user/character/show',
        operationId: 'userCharacterShow',
        description: 'Needs role: user',
        summary: 'Return the logged-in EVE character.',
        security: [['Session' => []]],
        tags: ['Character'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The logged-in EVE character.',
                content: new OA\JsonContent(ref: '#/components/schemas/Character'),
            ),
            new OA\Response(response: '403', description: 'Not authorized'),
        ],
    )]
    public function show(): ResponseInterface
    {
        return $this->withJson($this->getUser($this->userAuth));
    }

    #[OA\Get(
        path: '/user/character/find-character/{name}',
        operationId: 'findCharacter',
        description: 'Needs role: user-admin, user-manager, user-chars',
        summary: 'Returns a list of characters (together with the name of the player account/main' .
            ' character) that matches the name (partial matching).',
        security: [['Session' => []]],
        tags: ['Character'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                description: 'Name of the character (min. 3 characters).',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', minLength: 3),
            ),
            new OA\Parameter(
                name: 'currentOnly',
                description: 'Do not include old character names or moved characters. Defaults to false.',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['true', 'false']),
            ),
            new OA\Parameter(
                name: 'plugin',
                description: 'Include results from active service plugins. Defaults to false',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['true', 'false']),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of characters.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/SearchResult'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized'),
        ],
    )]
    public function findCharacter(
        string                 $name,
        ServerRequestInterface $request,
        PluginService          $pluginService,
    ): ResponseInterface {
        $name = trim($name);
        if (mb_strlen($name) < 3) {
            return $this->withJson([]);
        }

        $currentOnly = $this->getQueryParam($request, 'currentOnly') === 'true';
        $result = $this->repositoryFactory->getPlayerRepository()->findCharacters($name, $currentOnly);

        if ($this->getQueryParam($request, 'plugin') === 'true') {
            $result = $this->searchPlugins($name, $result, $pluginService);
        }

        return $this->withJson($result);
    }

    #[OA\Get(
        path: '/user/character/find-player/{name}',
        operationId: 'findPlayer',
        description: 'Needs role: group-manager',
        summary: 'Return a list of players that matches the main character name (partial matching).',
        security: [['Session' => []]],
        tags: ['Character'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                description: 'Name of the main character (min. 3 characters).',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', minLength: 3),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of main characters.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/SearchResult'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized'),
        ],
    )]
    public function findPlayer(string $name): ResponseInterface
    {
        $name = trim($name);
        if (mb_strlen($name) < 3) {
            return $this->withJson([]);
        }

        $retVal = [];
        $result = $this->repositoryFactory->getCharacterRepository()->findMainByNamePartialMatch($name);
        foreach ($result as $char) {
            $retVal[] = new SearchResult(
                $char->getId(),
                $char->getName(),
                $char->getPlayer()->getId(),
                $char->getPlayer()->getName(),
            );
        }

        return $this->withJson($retVal);
    }

    #[OA\Put(
        path: '/user/character/{id}/update',
        operationId: 'update',
        description: 'Needs role: user to update own characters or user-admin, user-manager, ' .
            'group-admin, app-admin, user-chars, tracking or watchlist to update any character.<br>' .
            'It also updates groups and verifies the OAuth token.',
        summary: 'Update a character with data from ESI.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Character'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'EVE character ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The character was updated.',
                content: new OA\JsonContent(type: 'integer'),
            ),
            new OA\Response(
                response: '204',
                description: 'The character was deleted because the owner hash changed.',
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Character not found on this account.'),
            new OA\Response(response: '503', description: 'ESI request failed.'),
        ],
    )]
    public function update(string $id, Account $accountService): ResponseInterface
    {
        // Note that users with the role tracking or watchlist should only update characters from
        // accounts from the respective lists. But there's no harm allowing them to update all as long as
        // this does not return any data. Otherwise this would need to check permissions like it's done
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

    #[OA\Post(
        path: '/user/character/add/{id}',
        operationId: 'userCharacterAdd',
        description: 'Needs role: user-admin',
        summary: 'Add an EVE character to the database on a new account.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Character'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'EVE character ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: '201', description: 'Successfully created account with character.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Character not found.'),
            new OA\Response(response: '409', description: 'Character already exists in local database.'),
            new OA\Response(response: '500', description: 'Role not found or ESI error.'),
        ],
    )]
    public function add(
        string $id,
        Account $accountService,
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

        // Check if the EVE character exists
        try {
            $eveChar = $this->esiData->fetchCharacter($charId);
        } catch (RuntimeException $e) {
            if ($e->getCode() === 404) {
                return $this->withJson('Character not found.', 404);
            } elseif ($e->getCode() === 410) {
                return $this->withJson('Character has been deleted.', 404);
            } {
                return $this->withJson('ESI error.', 500);
            }
        }

        // Create a new account
        $char = $accountService->createNewPlayerWithMain($charId, $eveChar->getName());
        $char->getPlayer()->addRole($userRole[0]);
        $this->objectManager->persist($char->getPlayer());
        $this->objectManager->persist($char);

        return $this->flushAndReturn(201);
    }

    /**
     * @param SearchResult[] $result
     * @return SearchResult[]
     */
    private function searchPlugins(string $name, array $result, PluginService $pluginService): array
    {
        foreach ($pluginService->getActivePluginsWithImplementation() as $plugin) {
            if (!$plugin->getServiceImplementation()) {
                continue;
            }

            try {
                $pluginResults = $plugin->getServiceImplementation()->search($name);
            } catch (Exception) {
                continue;
            }

            foreach ($pluginResults as $pluginResult) {
                $char = $this->repositoryFactory->getCharacterRepository()->find($pluginResult->getCharacterId());
                if ($char && !$this->hasResult($result, $char)) {
                    $result[] = new SearchResult(
                        $char->getId(),
                        $char->getName(),
                        $char->getPlayer()->getId(),
                        $char->getPlayer()->getName(),
                    );
                }
            }
        }

        return $result;
    }

    /**
     * @param SearchResult[] $result
     */
    private function hasResult(array $result, Character $char): bool
    {
        foreach ($result as $item) {
            if (
                $item->characterId === $char->getId() &&
                $item->characterName === $char->getName() &&
                $item->playerId === $char->getPlayer()->getId()
            ) {
                return true;
            }
        }
        return false;
    }
}
