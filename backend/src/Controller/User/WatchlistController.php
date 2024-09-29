<?php

/** @noinspection PhpUnused */

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
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="Watchlist",
 *     description="View and edit watchlists."
 * )
 */
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

    /**
     * @OA\Post(
     *     path="/user/watchlist/create",
     *     operationId="watchlistCreate",
     *     summary="Create a watchlist.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="name",
     *                     description="Name of the watchlist.",
     *                     type="string",
     *                     maxLength=32,
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="The new watchlist.",
     *         @OA\JsonContent(ref="#/components/schemas/Watchlist")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Watchlist name is missing."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/rename",
     *     operationId="watchlistRename",
     *     summary="Rename a watchlist.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the watchlist.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="name",
     *                     description="New name for the watchlist.",
     *                     type="string",
     *                     maxLength=32,
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Watchlist was renamed.",
     *         @OA\JsonContent(ref="#/components/schemas/Watchlist")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Watchlist name is missing."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Watchlist not found."
     *     ),
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/user/watchlist/{id}/delete",
     *     operationId="watchlistDelete",
     *     summary="Delete a watchlist.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the watchlist.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Watchlist was deleted."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Watchlist not found."
     *     )
     * )
     */
    public function delete(string $id): ResponseInterface
    {
        $watchlist = $this->watchlistRepository->find($id);
        if ($watchlist === null) {
            return $this->response->withStatus(404);
        }

        $this->objectManager->remove($watchlist);

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/lock-watchlist-settings/{lock}",
     *     operationId="watchlistLockWatchlistSettings",
     *     summary="Lock or unlock the watchlist settings.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the watchlist.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="lock",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", enum={"0", "1"})
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Setting was set.",
     *         @OA\JsonContent(ref="#/components/schemas/Watchlist")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Watchlist not found."
     *     ),
     * )
     */
    public function lockWatchlistSettings(string $id, string $lock): ResponseInterface
    {
        $watchlist = $this->watchlistRepository->find($id);
        if ($watchlist === null) {
            return $this->response->withStatus(404);
        }

        $watchlist->setLockWatchlistSettings((bool) $lock);

        return $this->flushAndReturn(200, $watchlist);
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/listAll",
     *     operationId="watchlistListAll",
     *     summary="Lists all watchlists.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of watchlists.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Watchlist"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function listAll(): ResponseInterface
    {
        return $this->withJson($this->watchlistRepository->findBy([], ['name' => 'ASC']));
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/list-available",
     *     operationId="watchlistListAvailable",
     *     summary="Lists all watchlists with view permission.",
     *     description="Needs role: watchlist",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of watchlists.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Watchlist"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/user/watchlist/list-available-manage",
     *     operationId="watchlistListAvailableManage",
     *     summary="Lists all watchlists with manage permission.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of watchlists.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Watchlist"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/players",
     *     operationId="watchlistPlayers",
     *     summary="List of player accounts that have characters in one of the configured alliances or corporations and additionally have other characters in another player (not NPC) corporation that is not on the allowlist and have not been manually excluded.",
     *     description="Needs role: watchlist",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of players.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/players-kicklist",
     *     operationId="watchlistPlayersKicklist",
     *     summary="Accounts from the watchlist with members in one of the alliances or corporations from the kicklist.",
     *     description="Needs role: watchlist",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of players.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function playersKicklist(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getKicklist((int) $id));
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/exemption/list",
     *     operationId="watchlistExemptionList",
     *     summary="List of exempt players.",
     *     description="Needs role: watchlist, watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of players, only ID and name properties are included.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/exemption/add/{player}",
     *     operationId="watchlistExemptionAdd",
     *     summary="Add player to exemption list.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         required=true,
     *         description="Player ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Player added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Player not found."
     *     )
     * )
     */
    public function exemptionAdd(string $id, string $player, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::EXEMPTION, (int) $player);
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/exemption/remove/{player}",
     *     operationId="watchlistExemptionRemove",
     *     summary="Remove player from exemption list.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         required=true,
     *         description="Player ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Player removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Player not found."
     *     )
     * )
     */
    public function exemptionRemove(string $id, string $player, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::EXEMPTION, (int) $player);
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/corporation/list",
     *     operationId="watchlistCorporationList",
     *     summary="List of corporations for this list.",
     *     description="Needs role: watchlist, watchlist-manager, watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of corporation.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corporationList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, null, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::CORPORATION));
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/corporation/add/{corporation}",
     *     operationId="watchlistCorporationAdd",
     *     summary="Add corporation to the list.",
     *     description="Needs role: watchlist-manager, watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="corporation",
     *         in="path",
     *         required=true,
     *         description="Corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Corporation added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Corporation not found."
     *     )
     * )
     */
    public function corporationAdd(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER, true, true)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::CORPORATION, (int) $corporation);
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/corporation/remove/{corporation}",
     *     operationId="watchlistCorporationRemove",
     *     summary="Remove corporation from the list.",
     *     description="Needs role: watchlist-manager, watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="corporation",
     *         in="path",
     *         required=true,
     *         description="Corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Corporation removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Corporation not found."
     *     )
     * )
     */
    public function corporationRemove(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER, true, true)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::CORPORATION, (int) $corporation);
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/alliance/list",
     *     operationId="watchlistAllianceList",
     *     summary="List of alliances for this list.",
     *     description="Needs role: watchlist, watchlist-manager, watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of alliances.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Alliance"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allianceList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, null, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::ALLIANCE));
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/alliance/add/{alliance}",
     *     operationId="watchlistAllianceAdd",
     *     summary="Add alliance to the list.",
     *     description="Needs role: watchlist-manager, watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="alliance",
     *         in="path",
     *         required=true,
     *         description="Alliance ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Alliance added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Alliance not found."
     *     )
     * )
     */
    public function allianceAdd(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER, true, true)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::ALLIANCE, (int) $alliance);
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/alliance/remove/{alliance}",
     *     operationId="watchlistAllianceRemove",
     *     summary="Remove alliance from the list.",
     *     description="Needs role: watchlist-manager, watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="alliance",
     *         in="path",
     *         required=true,
     *         description="Alliance ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Alliance removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Alliance not found."
     *     )
     * )
     */
    public function allianceRemove(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER, true, true)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::ALLIANCE, (int) $alliance);
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/group/list",
     *     operationId="watchlistGroupList",
     *     summary="List of groups with access to this list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of groups.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Group"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupList(string $id): ResponseInterface
    {
        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::GROUP));
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/group/add/{group}",
     *     operationId="watchlistGroupAdd",
     *     summary="Add access group to the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="group",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupAdd(string $id, string $group, Account $account): ResponseInterface
    {
        $response = $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::GROUP, (int) $group);

        if ($response->getStatusCode() === 204) {
            $account->syncWatchlistRole();
            $this->objectManager->flush();
        }

        return $response;
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/group/remove/{group}",
     *     operationId="watchlistGroupRemove",
     *     summary="Remove access group from the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="group",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupRemove(string $id, string $group, Account $account): ResponseInterface
    {
        $response = $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::GROUP, (int) $group);

        if ($response->getStatusCode() === 204) {
            $account->syncWatchlistRole();
            $this->objectManager->flush();
        }

        return $response;
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/manager-group/list",
     *     operationId="watchlistManagerGroupList",
     *     summary="List of groups with manager access to this list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of groups.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Group"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function managerGroupList(string $id): ResponseInterface
    {
        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::MANAGER_GROUP));
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/manager-group/add/{group}",
     *     operationId="watchlistManagerGroupAdd",
     *     summary="Add manager access group to the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="group",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function managerGroupAdd(string $id, string $group, Account $account): ResponseInterface
    {
        $response = $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::MANAGER_GROUP, (int) $group);

        if ($response->getStatusCode() === 204) {
            $account->syncWatchlistManagerRole();
            $this->objectManager->flush();
        }

        return $response;
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/manager-group/remove/{group}",
     *     operationId="watchlistManagerGroupRemove",
     *     summary="Remove manager access group from the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="group",
     *         in="path",
     *         required=true,
     *         description="Group ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Group removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function managerGroupRemove(string $id, string $group, Account $account): ResponseInterface
    {
        $response = $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::MANAGER_GROUP, (int) $group);

        if ($response->getStatusCode() === 204) {
            $account->syncWatchlistManagerRole();
            $this->objectManager->flush();
        }

        return $response;
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/kicklist-corporation/list",
     *     operationId="watchlistKicklistCorporationList",
     *     summary="List of corporations for the kicklist.",
     *     description="Needs role: watchlist, watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of corporation.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function kicklistCorporationList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::KICKLIST_CORPORATION));
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/kicklist-corporation/add/{corporation}",
     *     operationId="watchlistKicklistCorporationAdd",
     *     summary="Add corporation to the kicklist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="corporation",
     *         in="path",
     *         required=true,
     *         description="Corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Corporation added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Corporation not found."
     *     )
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/kicklist-corporation/remove/{corporation}",
     *     operationId="watchlistKicklistCorporationRemove",
     *     summary="Remove corporation from the kicklist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="corporation",
     *         in="path",
     *         required=true,
     *         description="Corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Corporation removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Corporation not found."
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/kicklist-alliance/list",
     *     operationId="watchlistKicklistAllianceList",
     *     summary="List of alliances for the kicklist.",
     *     description="Needs role: watchlist, watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of alliances.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Alliance"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function kicklistAllianceList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::KICKLIST_ALLIANCE));
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/kicklist-alliance/add/{alliance}",
     *     operationId="watchlistKicklistAllianceAdd",
     *     summary="Add alliance to the kicklist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="alliance",
     *         in="path",
     *         required=true,
     *         description="Alliance ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Alliance added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Alliance not found."
     *     )
     * )
     */
    public function kicklistAllianceAdd(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::KICKLIST_ALLIANCE, (int) $alliance);
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/kicklist-alliance/remove/{alliance}",
     *     operationId="watchlistKicklistAllianceRemove",
     *     summary="Remove alliance from the kicklist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="alliance",
     *         in="path",
     *         required=true,
     *         description="Alliance ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Alliance removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Alliance not found."
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/allowlist-corporation/list",
     *     operationId="watchlistAllowlistCorporationList",
     *     summary="List of corporations for the corporation allowlist.",
     *     description="Needs role: watchlist, watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of corporation.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/allowlist-corporation/add/{corporation}",
     *     operationId="watchlistAllowlistCorporationAdd",
     *     summary="Add corporation to the corporation allowlist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="corporation",
     *         in="path",
     *         required=true,
     *         description="Corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Corporation added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Corporation not found."
     *     )
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/allowlist-corporation/remove/{corporation}",
     *     operationId="watchlistAllowlistCorporationRemove",
     *     summary="Remove corporation from the corporation allowlist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="corporation",
     *         in="path",
     *         required=true,
     *         description="Corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Corporation removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Corporation not found."
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/user/watchlist/{id}/allowlist-alliance/list",
     *     operationId="watchlistAllowlistAllianceList",
     *     summary="List of alliances for the alliance allowlist.",
     *     description="Needs role: watchlist, watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="List of alliances.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Alliance"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allowlistAllianceList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::ALLOWLIST_ALLIANCE));
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/allowlist-alliance/add/{alliance}",
     *     operationId="watchlistAllowlistAllianceAdd",
     *     summary="Add alliance to the alliance allowlist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="alliance",
     *         in="path",
     *         required=true,
     *         description="Alliance ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Alliance added."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Alliance not found."
     *     )
     * )
     */
    public function allowlistAllianceAdd(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int)$id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::ALLOWLIST_ALLIANCE, (int) $alliance);
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/{id}/allowlist-alliance/remove/{alliance}",
     *     operationId="watchlistAllowlistAllianceRemove",
     *     summary="Remove alliance from the alliance allowlist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Watchlist ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="alliance",
     *         in="path",
     *         required=true,
     *         description="Alliance ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Alliance removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="List or Alliance not found."
     *     )
     * )
     */
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
