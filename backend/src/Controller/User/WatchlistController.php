<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Entity\Role;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\WatchlistRepository;
use Neucore\Service\Account;
use Neucore\Service\ObjectManager;
use Neucore\Service\UserAuth;
use Neucore\Service\Watchlist;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[OA\Tag(name: 'Watchlist', description: 'View and edit watchlists.')]
class WatchlistController extends BaseController
{
    private const ACTION_ADD = 'add';

    private const ACTION_REMOVE = 'remove';

    private Watchlist $watchlistService;

    private WatchlistRepository $watchlistRepository;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        Watchlist $watchlist
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->watchlistService = $watchlist;
        $this->watchlistRepository = $repositoryFactory->getWatchlistRepository();
    }

    #[OA\Post(
        path: '/user/watchlist/create',
        operationId: 'watchlistCreate',
        description: 'Needs role: watchlist-admin',
        summary: 'Create a watchlist.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            description: 'Name of the watchlist.',
                            type: 'string',
                            maxLength: 32
                        )
                    ],
                    type: 'object',
                )
            )
        ),
        tags: ['Watchlist'],
        responses: [
            new OA\Response(
                response: '201',
                description: 'The new watchlist.',
                content: new OA\JsonContent(ref: '#/components/schemas/Watchlist')
            ),
            new OA\Response(response: '400', description: 'Watchlist name is missing.'),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $name = $this->sanitizePrintable($this->getBodyParam($request, 'name', ''));
        if ($name === '') {
            return $this->response->withStatus(400);
        }

        $watchlist = new \Neucore\Entity\Watchlist();
        $watchlist->setName($name);
        $this->objectManager->persist($watchlist);

        return $this->flushAndReturn(201, $watchlist);
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/rename',
        operationId: 'watchlistRename',
        description: 'Needs role: watchlist-admin',
        summary: 'Rename a watchlist.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            description: 'New name for the watchlist.',
                            type: 'string',
                            maxLength: 32
                        )
                    ],
                    type: 'object',
                )
            )
        ),
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the watchlist.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Watchlist was renamed.',
                content: new OA\JsonContent(ref: '#/components/schemas/Watchlist')
            ),
            new OA\Response(response: '400', description: 'Watchlist name is missing.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Watchlist not found.')
        ],
    )]
    public function rename(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $name = $this->sanitizePrintable($this->getBodyParam($request, 'name', ''));
        if ($name === '') {
            return $this->response->withStatus(400);
        }

        $watchlist = $this->watchlistRepository->find($id);
        if ($watchlist === null) {
            return $this->response->withStatus(404);
        }

        $watchlist->setName($name);

        return $this->flushAndReturn(200, $watchlist);
    }

    #[OA\Delete(
        path: '/user/watchlist/{id}/delete',
        operationId: 'watchlistDelete',
        description: 'Needs role: watchlist-admin',
        summary: 'Delete a watchlist.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the watchlist.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Watchlist was deleted.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Watchlist not found.')
        ],
    )]
    public function delete(string $id): ResponseInterface
    {
        $watchlist = $this->watchlistRepository->find($id);
        if ($watchlist === null) {
            return $this->response->withStatus(404);
        }

        $this->objectManager->remove($watchlist);

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/lock-watchlist-settings/{lock}',
        operationId: 'watchlistLockWatchlistSettings',
        description: 'Needs role: watchlist-admin',
        summary: 'Lock or unlock the watchlist settings.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the watchlist.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'lock',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['0', '1'])
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Setting was set.',
                content: new OA\JsonContent(ref: '#/components/schemas/Watchlist')
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Watchlist not found.')
        ],
    )]
    public function lockWatchlistSettings(string $id, string $lock): ResponseInterface
    {
        $watchlist = $this->watchlistRepository->find($id);
        if ($watchlist === null) {
            return $this->response->withStatus(404);
        }

        $watchlist->setLockWatchlistSettings((bool) $lock);

        return $this->flushAndReturn(200, $watchlist);
    }

    #[OA\Get(
        path: '/user/watchlist/listAll',
        operationId: 'watchlistListAll',
        description: 'Needs role: watchlist-admin',
        summary: 'Lists all watchlists.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of watchlists.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Watchlist')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function listAll(): ResponseInterface
    {
        return $this->withJson($this->watchlistRepository->findBy([], ['name' => 'ASC']));
    }

    #[OA\Get(
        path: '/user/watchlist/list-available',
        operationId: 'watchlistListAvailable',
        description: 'Needs role: watchlist',
        summary: 'Lists all watchlists with view permission.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of watchlists.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Watchlist')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function listAvailable(UserAuth $userAuth): ResponseInterface
    {
        $result = [];
        foreach ($this->watchlistRepository->findBy([], ['name' => 'ASC']) as $list) {
            if ($this->checkPermission($list->getId(), $userAuth, Role::WATCHLIST)) {
                $result[] = $list;
            }
        }
        return $this->withJson($result);
    }

    #[OA\Get(
        path: '/user/watchlist/list-available-manage',
        operationId: 'watchlistListAvailableManage',
        description: 'Needs role: watchlist-manager',
        summary: 'Lists all watchlists with manage permission.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of watchlists.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Watchlist')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function listAvailableManage(UserAuth $userAuth): ResponseInterface
    {
        $result = [];
        foreach ($this->watchlistRepository->findBy([]) as $list) {
            if ($this->checkPermission($list->getId(), $userAuth, Role::WATCHLIST_MANAGER)) {
                $result[] = $list;
            }
        }
        return $this->withJson($result);
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/players',
        operationId: 'watchlistPlayers',
        description: 'Needs role: watchlist',
        summary: 'List of player accounts that have characters in one of the configured alliances' .
            ' or corporations and additionally have other characters in another player (not NPC) ' .
            'corporation that is not on the allowlist and have not been manually excluded.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of players.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Player')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function players(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST)) {
            return $this->response->withStatus(403);
        }

        $players = array_map(function (Player $player) {
            return $player->jsonSerialize(true);
        }, $this->watchlistService->getWarningList((int) $id));

        return $this->withJson($players);
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/players-kicklist',
        operationId: 'watchlistPlayersKicklist',
        description: 'Needs role: watchlist',
        summary: 'Accounts from the watchlist with members in one of the alliances or corporations' .
            ' from the kicklist.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of players.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Player')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function playersKicklist(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getKicklist((int) $id));
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/exemption/list',
        operationId: 'watchlistExemptionList',
        description: 'Needs role: watchlist, watchlist-manager',
        summary: 'List of exempt players.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of players, only ID and name properties are included.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Player')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function exemptionList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        $data = array_map(function (Player $player) {
            return $player->jsonSerialize(true);
        }, $this->watchlistService->getList((int) $id, 'exemption'));

        return $this->withJson($data);
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/exemption/add/{player}',
        operationId: 'watchlistExemptionAdd',
        description: 'Needs role: watchlist-manager',
        summary: 'Add player to exemption list.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'player',
                description: 'Player ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Player added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Player not found.')
        ],
    )]
    public function exemptionAdd(string $id, string $player, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::EXEMPTION, (int) $player);
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/exemption/remove/{player}',
        operationId: 'watchlistExemptionRemove',
        description: 'Needs role: watchlist-manager',
        summary: 'Remove player from exemption list.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'player',
                description: 'Player ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Player removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Player not found.')
        ],
    )]
    public function exemptionRemove(string $id, string $player, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::EXEMPTION, (int) $player);
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/corporation/list',
        operationId: 'watchlistCorporationList',
        description: 'Needs role: watchlist, watchlist-manager, watchlist-admin',
        summary: 'List of corporations for this list.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of corporation.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Corporation')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function corporationList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, null, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::CORPORATION));
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/corporation/add/{corporation}',
        operationId: 'watchlistCorporationAdd',
        description: 'Needs role: watchlist-manager, watchlist-admin',
        summary: 'Add corporation to the list.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'corporation',
                description: 'Corporation ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Corporation added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Corporation not found.')
        ],
    )]
    public function corporationAdd(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER, true, true)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::CORPORATION, (int) $corporation);
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/corporation/remove/{corporation}',
        operationId: 'watchlistCorporationRemove',
        description: 'Needs role: watchlist-manager, watchlist-admin',
        summary: 'Remove corporation from the list.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'corporation',
                description: 'Corporation ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Corporation removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Corporation not found.')
        ],
    )]
    public function corporationRemove(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER, true, true)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::CORPORATION, (int) $corporation);
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/alliance/list',
        operationId: 'watchlistAllianceList',
        description: 'Needs role: watchlist, watchlist-manager, watchlist-admin',
        summary: 'List of alliances for this list.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of alliances.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Alliance')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function allianceList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, null, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::ALLIANCE));
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/alliance/add/{alliance}',
        operationId: 'watchlistAllianceAdd',
        description: 'Needs role: watchlist-manager, watchlist-admin',
        summary: 'Add alliance to the list.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'alliance',
                description: 'Alliance ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Alliance added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Alliance not found.')
        ],
    )]
    public function allianceAdd(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER, true, true)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::ALLIANCE, (int) $alliance);
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/alliance/remove/{alliance}',
        operationId: 'watchlistAllianceRemove',
        description: 'Needs role: watchlist-manager, watchlist-admin',
        summary: 'Remove alliance from the list.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'alliance',
                description: 'Alliance ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Alliance removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Alliance not found.')
        ],
    )]
    public function allianceRemove(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER, true, true)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::ALLIANCE, (int) $alliance);
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/group/list',
        operationId: 'watchlistGroupList',
        description: 'Needs role: watchlist-admin',
        summary: 'List of groups with access to this list.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of groups.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Group')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function groupList(string $id): ResponseInterface
    {
        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::GROUP));
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/group/add/{group}',
        operationId: 'watchlistGroupAdd',
        description: 'Needs role: watchlist-admin',
        summary: 'Add access group to the list.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'group',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group added.'),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function groupAdd(string $id, string $group, Account $account): ResponseInterface
    {
        $response = $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::GROUP, (int) $group);

        if ($response->getStatusCode() === 204) {
            $account->syncWatchlistRole();
            $this->objectManager->flush();
        }

        return $response;
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/group/remove/{group}',
        operationId: 'watchlistGroupRemove',
        description: 'Needs role: watchlist-admin',
        summary: 'Remove access group from the list.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'group',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group removed.'),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function groupRemove(string $id, string $group, Account $account): ResponseInterface
    {
        $response = $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::GROUP, (int) $group);

        if ($response->getStatusCode() === 204) {
            $account->syncWatchlistRole();
            $this->objectManager->flush();
        }

        return $response;
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/manager-group/list',
        operationId: 'watchlistManagerGroupList',
        description: 'Needs role: watchlist-admin',
        summary: 'List of groups with manager access to this list.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of groups.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Group')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function managerGroupList(string $id): ResponseInterface
    {
        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::MANAGER_GROUP));
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/manager-group/add/{group}',
        operationId: 'watchlistManagerGroupAdd',
        description: 'Needs role: watchlist-admin',
        summary: 'Add manager access group to the list.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'group',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group added.'),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function managerGroupAdd(string $id, string $group, Account $account): ResponseInterface
    {
        $response = $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::MANAGER_GROUP, (int) $group);

        if ($response->getStatusCode() === 204) {
            $account->syncWatchlistManagerRole();
            $this->objectManager->flush();
        }

        return $response;
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/manager-group/remove/{group}',
        operationId: 'watchlistManagerGroupRemove',
        description: 'Needs role: watchlist-admin',
        summary: 'Remove manager access group from the list.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'group',
                description: 'Group ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Group removed.'),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function managerGroupRemove(string $id, string $group, Account $account): ResponseInterface
    {
        $response = $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::MANAGER_GROUP, (int) $group);

        if ($response->getStatusCode() === 204) {
            $account->syncWatchlistManagerRole();
            $this->objectManager->flush();
        }

        return $response;
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/kicklist-corporation/list',
        operationId: 'watchlistKicklistCorporationList',
        description: 'Needs role: watchlist, watchlist-manager',
        summary: 'List of corporations for the kicklist.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of corporation.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Corporation')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function kicklistCorporationList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::KICKLIST_CORPORATION));
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/kicklist-corporation/add/{corporation}',
        operationId: 'watchlistKicklistCorporationAdd',
        description: 'Needs role: watchlist-manager',
        summary: 'Add corporation to the kicklist.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'corporation',
                description: 'Corporation ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Corporation added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Corporation not found.')
        ],
    )]
    public function kicklistCorporationAdd(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int)
            $id,
            self::ACTION_ADD,
            Watchlist::KICKLIST_CORPORATION,
            (int) $corporation
        );
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/kicklist-corporation/remove/{corporation}',
        operationId: 'watchlistKicklistCorporationRemove',
        description: 'Needs role: watchlist-manager',
        summary: 'Remove corporation from the kicklist.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'corporation',
                description: 'Corporation ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Corporation removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Corporation not found.')
        ],
    )]
    public function kicklistCorporationRemove(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int) $id,
            self::ACTION_REMOVE,
            Watchlist::KICKLIST_CORPORATION,
            (int) $corporation
        );
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/kicklist-alliance/list',
        operationId: 'watchlistKicklistAllianceList',
        description: 'Needs role: watchlist, watchlist-manager',
        summary: 'List of alliances for the kicklist.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of alliances.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Alliance')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function kicklistAllianceList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::KICKLIST_ALLIANCE));
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/kicklist-alliance/add/{alliance}',
        operationId: 'watchlistKicklistAllianceAdd',
        description: 'Needs role: watchlist-manager',
        summary: 'Add alliance to the kicklist.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'alliance',
                description: 'Alliance ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Alliance added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Alliance not found.')
        ],
    )]
    public function kicklistAllianceAdd(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::KICKLIST_ALLIANCE, (int) $alliance);
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/kicklist-alliance/remove/{alliance}',
        operationId: 'watchlistKicklistAllianceRemove',
        description: 'Needs role: watchlist-manager',
        summary: 'Remove alliance from the kicklist.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'alliance',
                description: 'Alliance ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Alliance removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Alliance not found.')
        ],
    )]
    public function kicklistAllianceRemove(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int) $id,
            self::ACTION_REMOVE,
            Watchlist::KICKLIST_ALLIANCE,
            (int) $alliance
        );
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/allowlist-corporation/list',
        operationId: 'watchlistAllowlistCorporationList',
        description: 'Needs role: watchlist, watchlist-manager',
        summary: 'List of corporations for the corporation allowlist.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of corporation.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Corporation')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function allowlistCorporationList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        $data = array_map(function (Corporation $corporation) {
            return $corporation->jsonSerialize(false, true);
        }, $this->watchlistService->getList((int) $id, Watchlist::ALLOWLIST_CORPORATION));

        return $this->withJson($data);
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/allowlist-corporation/add/{corporation}',
        operationId: 'watchlistAllowlistCorporationAdd',
        description: 'Needs role: watchlist-manager',
        summary: 'Add corporation to the corporation allowlist.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'corporation',
                description: 'Corporation ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Corporation added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Corporation not found.')
        ],
    )]
    public function allowlistCorporationAdd(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int) $id,
            self::ACTION_ADD,
            Watchlist::ALLOWLIST_CORPORATION,
            (int) $corporation
        );
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/allowlist-corporation/remove/{corporation}',
        operationId: 'watchlistAllowlistCorporationRemove',
        description: 'Needs role: watchlist-manager',
        summary: 'Remove corporation from the corporation allowlist.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'corporation',
                description: 'Corporation ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Corporation removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Corporation not found.')
        ],
    )]
    public function allowlistCorporationRemove(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int) $id,
            self::ACTION_REMOVE,
            Watchlist::ALLOWLIST_CORPORATION,
            (int) $corporation
        );
    }

    #[OA\Get(
        path: '/user/watchlist/{id}/allowlist-alliance/list',
        operationId: 'watchlistAllowlistAllianceList',
        description: 'Needs role: watchlist, watchlist-manager',
        summary: 'List of alliances for the alliance allowlist.',
        security: [['Session' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of alliances.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Alliance')
                )
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
    public function allowlistAllianceList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::ALLOWLIST_ALLIANCE));
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/allowlist-alliance/add/{alliance}',
        operationId: 'watchlistAllowlistAllianceAdd',
        description: 'Needs role: watchlist-manager',
        summary: 'Add alliance to the alliance allowlist.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'alliance',
                description: 'Alliance ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Alliance added.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Alliance not found.')
        ],
    )]
    public function allowlistAllianceAdd(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::ALLOWLIST_ALLIANCE, (int) $alliance);
    }

    #[OA\Put(
        path: '/user/watchlist/{id}/allowlist-alliance/remove/{alliance}',
        operationId: 'watchlistAllowlistAllianceRemove',
        description: 'Needs role: watchlist-manager',
        summary: 'Remove alliance from the alliance allowlist.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Watchlist'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Watchlist ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'alliance',
                description: 'Alliance ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Alliance removed.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'List or Alliance not found.')
        ],
    )]
    public function allowlistAllianceRemove(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int) $id,
            self::ACTION_REMOVE,
            Watchlist::ALLOWLIST_ALLIANCE,
            (int) $alliance
        );
    }

    /**
     * Checks if logged-in user is member of a group that may see or manage this watchlist.
     *
     * @param int $id Watchlist ID
     * @param UserAuth $userAuth
     * @param string|null $roleName Role::WATCHLIST or Role::WATCHLIST_MANAGER or null if both give permission
     * @param bool $admin True if Role::WATCHLIST_ADMIN gives permission
     * @param bool $checkSettingsLock True if watchlist::$lockWatchlistSettings needs to be checked and only allow
     *        watchlist-admin if it is true
     * @return bool
     */
    private function checkPermission(
        int $id,
        UserAuth $userAuth,
        string $roleName = null,
        bool $admin = false,
        bool $checkSettingsLock = false
    ): bool {
        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($id);
        if ($watchlist === null) {
            return false;
        }

        // check admin
        if ($admin && in_array(Role::WATCHLIST_ADMIN, $userAuth->getRoles())) {
            return true;
        }

        // check lock
        if (
            $checkSettingsLock &&
            $watchlist->getLockWatchlistSettings() &&
            ! in_array(Role::WATCHLIST_ADMIN, $userAuth->getRoles())
        ) {
            return false;
        }

        // get groups
        if ($roleName === Role::WATCHLIST) {
            $groups = $watchlist->getGroups();
        } elseif ($roleName === Role::WATCHLIST_MANAGER) {
            $groups = $watchlist->getManagerGroups();
        } else { // both roles give permission
            $groups = array_merge($watchlist->getGroups(), $watchlist->getManagerGroups());
        }

        // check groups
        $playerGroupIds = $this->getUser($userAuth)->getPlayer()->getGroupIds();
        foreach ($groups as $group) {
            if (in_array($group->getId(), $playerGroupIds)) {
                return true;
            }
        }

        return false;
    }

    private function addOrRemoveEntity(int $id, string $action, string $type, int $entityId): ResponseInterface
    {
        $entity = null;
        if ($type === Watchlist::EXEMPTION) {
            $entity = $this->repositoryFactory->getPlayerRepository()->find($entityId);
        } elseif (in_array(
            $type,
            [Watchlist::CORPORATION, Watchlist::KICKLIST_CORPORATION, Watchlist::ALLOWLIST_CORPORATION]
        )) {
            $entity = $this->repositoryFactory->getCorporationRepository()->find($entityId);
        } elseif (in_array(
            $type,
            [Watchlist::ALLIANCE, Watchlist::KICKLIST_ALLIANCE, Watchlist::ALLOWLIST_ALLIANCE]
        )) {
            $entity = $this->repositoryFactory->getAllianceRepository()->find($entityId);
        } elseif ($type === Watchlist::GROUP || $type === Watchlist::MANAGER_GROUP) {
            $entity = $this->repositoryFactory->getGroupRepository()->find($entityId);
        }

        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($id);

        if ($entity === null || $watchlist === null) {
            return $this->response->withStatus(404);
        }

        if ($action === self::ACTION_ADD) {
            if ($entity instanceof Player) {
                $watchlist->addExemption($entity);
            } elseif ($entity instanceof Corporation && $type === Watchlist::CORPORATION) {
                $watchlist->addCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === Watchlist::ALLIANCE) {
                $watchlist->addAlliance($entity);
            } elseif ($entity instanceof Group && $type === Watchlist::GROUP) {
                $watchlist->addGroup($entity);
            } elseif ($entity instanceof Group && $type === Watchlist::MANAGER_GROUP) {
                $watchlist->addManagerGroup($entity);
            } elseif ($entity instanceof Corporation && $type === Watchlist::KICKLIST_CORPORATION) {
                $watchlist->addKicklistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === Watchlist::KICKLIST_ALLIANCE) {
                $watchlist->addKicklistAlliance($entity);
            } elseif ($entity instanceof Corporation && $type === Watchlist::ALLOWLIST_CORPORATION) {
                $watchlist->addAllowlistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === Watchlist::ALLOWLIST_ALLIANCE) {
                $watchlist->addAllowlistAlliance($entity);
            }
        } elseif ($action === self::ACTION_REMOVE) {
            if ($entity instanceof Player) {
                $watchlist->removeExemption($entity);
            } elseif ($entity instanceof Corporation && $type === Watchlist::CORPORATION) {
                $watchlist->removeCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === Watchlist::ALLIANCE) {
                $watchlist->removeAlliance($entity);
            } elseif ($entity instanceof Group && $type === Watchlist::GROUP) {
                $watchlist->removeGroup($entity);
            } elseif ($entity instanceof Group && $type === Watchlist::MANAGER_GROUP) {
                $watchlist->removeManagerGroup($entity);
            } elseif ($entity instanceof Corporation && $type === Watchlist::KICKLIST_CORPORATION) {
                $watchlist->removeKicklistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === Watchlist::KICKLIST_ALLIANCE) {
                $watchlist->removeKicklistAlliance($entity);
            } elseif ($entity instanceof Corporation && $type === Watchlist::ALLOWLIST_CORPORATION) {
                $watchlist->removeAllowlistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === Watchlist::ALLOWLIST_ALLIANCE) {
                $watchlist->removeAllowlistAlliance($entity);
            }
        }

        return $this->flushAndReturn(204);
    }
}
