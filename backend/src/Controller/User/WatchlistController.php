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
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * @OA\Tag(
 *     name="Watchlist",
 *     description="View and edit watchlists."
 * )
 */
class WatchlistController extends BaseController
{
    const ACTION_ADD = 'add';

    const ACTION_REMOVE = 'remove';

    /**
     * @var Watchlist
     */
    private $watchlistService;

    /**
     * @var WatchlistRepository
     */
    private $watchlistRepository;

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
     * @noinspection PhpUnused
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
        return $this->withJson($this->watchlistRepository->findBy([]));
    }

    /**
     * @noinspection PhpUnused
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
        foreach ($this->watchlistRepository->findBy([]) as $list) {
            if ($this->checkPermission($list->getId(), $userAuth, Role::WATCHLIST)) {
                $result[] = $list;
            }
        }
        return $this->withJson($result);
    }

    /**
     * @noinspection PhpUnused
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
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/players",
     *     operationId="watchlistPlayers",
     *     summary="List of player accounts that have characters in one of the configured alliances or corporations
                    and additionally have other characters in another player (not NPC) corporation that is not
                    whitelisted and have not been manually excluded.",
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
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST)) {
            return $this->response->withStatus(403);
        }

        $players = array_map(function (Player $player) {
            return $player->jsonSerialize(true);
        }, $this->watchlistService->getRedFlagList((int) $id));

        return $this->withJson($players);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/players-blacklist",
     *     operationId="watchlistPlayersBlacklist",
     *     summary="Accounts from the watchlist with members in one of the blacklisted alliances or corporations.",
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
    public function playersBlacklist(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getBlacklist((int) $id));
    }

    /**
     * @noinspection PhpUnused
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
        if (! $this->checkPermission((int) $id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        $data = array_map(function (Player $player) {
            return $player->jsonSerialize(true);
        }, $this->watchlistService->getList((int) $id, 'exemption'));

        return $this->withJson($data);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/exemption/add/{player}",
     *     operationId="watchlistExemptionAdd",
     *     summary="Add player to exemption list.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::EXEMPTION, (int) $player);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/exemption/remove/{player}",
     *     operationId="watchlistExemptionRemove",
     *     summary="Remove player from exemption list.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::EXEMPTION, (int) $player);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/corporation/list",
     *     operationId="watchlistCorporationList",
     *     summary="List of corporations for this list.",
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
    public function corporationList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::CORPORATION));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/corporation/add/{corporation}",
     *     operationId="watchlistCorporationAdd",
     *     summary="Add corporation to the list.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function corporationAdd(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::CORPORATION, (int) $corporation);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/corporation/remove/{corporation}",
     *     operationId="watchlistCorporationRemove",
     *     summary="Remove corporation from the list.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function corporationRemove(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::CORPORATION, (int) $corporation);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/alliance/list",
     *     operationId="watchlistAllianceList",
     *     summary="List of alliances for this list.",
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
    public function allianceList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::ALLIANCE));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/alliance/add/{alliance}",
     *     operationId="watchlistAllianceAdd",
     *     summary="Add alliance to the list.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function allianceAdd(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::ALLIANCE, (int) $alliance);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/alliance/remove/{alliance}",
     *     operationId="watchlistAllianceRemove",
     *     summary="Remove alliance from the list.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function allianceRemove(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_REMOVE, Watchlist::ALLIANCE, (int) $alliance);
    }

    /**
     * @noinspection PhpUnused
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
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/group/add/{group}",
     *     operationId="watchlistGroupAdd",
     *     summary="Add access group to the list.",
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
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/group/remove/{group}",
     *     operationId="watchlistGroupRemove",
     *     summary="Remove access group from the list.",
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
     * @noinspection PhpUnused
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
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/manager-group/add/{group}",
     *     operationId="watchlistManagerGroupAdd",
     *     summary="Add manager access group to the list.",
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
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/manager-group/remove/{group}",
     *     operationId="watchlistManagerGroupRemove",
     *     summary="Remove manager access group from the list.",
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
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/blacklist-corporation/list",
     *     operationId="watchlistBlacklistCorporationList",
     *     summary="List of corporations for the blacklist.",
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
    public function blacklistCorporationList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::BLACKLIST_CORPORATION));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/blacklist-corporation/add/{corporation}",
     *     operationId="watchlistBlacklistCorporationAdd",
     *     summary="Add corporation to the blacklist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function blacklistCorporationAdd(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int)
            $id,
            self::ACTION_ADD,
            Watchlist::BLACKLIST_CORPORATION,
            (int) $corporation
        );
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/blacklist-corporation/remove/{corporation}",
     *     operationId="watchlistBlacklistCorporationRemove",
     *     summary="Remove corporation from the blacklist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function blacklistCorporationRemove(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int) $id,
            self::ACTION_REMOVE,
            Watchlist::BLACKLIST_CORPORATION,
            (int) $corporation
        );
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/blacklist-alliance/list",
     *     operationId="watchlistBlacklistAllianceList",
     *     summary="List of alliances for the blacklist.",
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
    public function blacklistAllianceList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::BLACKLIST_ALLIANCE));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/blacklist-alliance/add/{alliance}",
     *     operationId="watchlistBlacklistAllianceAdd",
     *     summary="Add alliance to the blacklist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function blacklistAllianceAdd(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::BLACKLIST_ALLIANCE, (int) $alliance);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/blacklist-alliance/remove/{alliance}",
     *     operationId="watchlistBlacklistAllianceRemove",
     *     summary="Remove alliance from the blacklist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function blacklistAllianceRemove(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int) $id,
            self::ACTION_REMOVE,
            Watchlist::BLACKLIST_ALLIANCE,
            (int) $alliance
        );
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/whitelist-corporation/list",
     *     operationId="watchlistWhitelistCorporationList",
     *     summary="List of corporations for the corporation whitelist.",
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
    public function whitelistCorporationList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        $data = array_map(function (Corporation $corporation) {
            return $corporation->jsonSerialize(false, true);
        }, $this->watchlistService->getList((int) $id, Watchlist::WHITELIST_CORPORATION));

        return $this->withJson($data);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/whitelist-corporation/add/{corporation}",
     *     operationId="watchlistWhitelistCorporationAdd",
     *     summary="Add corporation to the corporation whitelist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function whitelistCorporationAdd(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int) $id,
            self::ACTION_ADD,
            Watchlist::WHITELIST_CORPORATION,
            (int) $corporation
        );
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/whitelist-corporation/remove/{corporation}",
     *     operationId="watchlistWhitelistCorporationRemove",
     *     summary="Remove corporation from the corporation whitelist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function whitelistCorporationRemove(string $id, string $corporation, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int) $id,
            self::ACTION_REMOVE,
            Watchlist::WHITELIST_CORPORATION,
            (int) $corporation
        );
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/whitelist-alliance/list",
     *     operationId="watchlistWhitelistAllianceList",
     *     summary="List of alliances for the alliance whitelist.",
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
    public function whitelistAllianceList(string $id, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlistService->getList((int) $id, Watchlist::WHITELIST_ALLIANCE));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/whitelist-alliance/add/{alliance}",
     *     operationId="watchlistWhitelistAllianceAdd",
     *     summary="Add alliance to the alliance whitelist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function whitelistAllianceAdd(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity((int) $id, self::ACTION_ADD, Watchlist::WHITELIST_ALLIANCE, (int) $alliance);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/whitelist-alliance/remove/{alliance}",
     *     operationId="watchlistWhitelistAllianceRemove",
     *     summary="Remove alliance from the alliance whitelist.",
     *     description="Needs role: watchlist-manager",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
     *         description="List or Player not found."
     *     )
     * )
     */
    public function whitelistAllianceRemove(string $id, string $alliance, UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission((int) $id, $userAuth, Role::WATCHLIST_MANAGER)) {
            return $this->response->withStatus(403);
        }

        return $this->addOrRemoveEntity(
            (int) $id,
            self::ACTION_REMOVE,
            Watchlist::WHITELIST_ALLIANCE,
            (int) $alliance
        );
    }

    /**
     * Checks if logged in user is member of a group that may see or manage this watchlist.
     */
    private function checkPermission(int $id, UserAuth $userAuth, string $roleName = null): bool
    {
        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($id);
        if ($watchlist === null) {
            return false;
        }

        $playerGroupIds = $this->getUser($userAuth)->getPlayer()->getGroupIds();

        if ($roleName === Role::WATCHLIST) {
            $groups = $watchlist->getGroups();
        } elseif ($roleName === Role::WATCHLIST_MANAGER) {
            $groups = $watchlist->getManagerGroups();
        } else { // both roles give permission
            $groups = array_merge($watchlist->getGroups(), $watchlist->getManagerGroups());
        }

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
            [Watchlist::CORPORATION, Watchlist::BLACKLIST_CORPORATION, Watchlist::WHITELIST_CORPORATION]
        )) {
            $entity = $this->repositoryFactory->getCorporationRepository()->find($entityId);
        } elseif (in_array(
            $type,
            [Watchlist::ALLIANCE, Watchlist::BLACKLIST_ALLIANCE, Watchlist::WHITELIST_ALLIANCE]
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
            } elseif ($entity instanceof Corporation && $type === Watchlist::BLACKLIST_CORPORATION) {
                $watchlist->addBlacklistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === Watchlist::BLACKLIST_ALLIANCE) {
                $watchlist->addBlacklistAlliance($entity);
            } elseif ($entity instanceof Corporation && $type === Watchlist::WHITELIST_CORPORATION) {
                $watchlist->addWhitelistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === Watchlist::WHITELIST_ALLIANCE) {
                $watchlist->addWhitelistAlliance($entity);
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
            } elseif ($entity instanceof Corporation && $type === Watchlist::BLACKLIST_CORPORATION) {
                $watchlist->removeBlacklistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === Watchlist::BLACKLIST_ALLIANCE) {
                $watchlist->removeBlacklistAlliance($entity);
            } elseif ($entity instanceof Corporation && $type === Watchlist::WHITELIST_CORPORATION) {
                $watchlist->removeWhitelistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === Watchlist::WHITELIST_ALLIANCE) {
                $watchlist->removeWhitelistAlliance($entity);
            }
        }

        return $this->flushAndReturn(204);
    }
}
