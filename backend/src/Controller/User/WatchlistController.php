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
    /**
     * @var Watchlist
     */
    private $watchlist;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        Watchlist $watchlist
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->watchlist = $watchlist;
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
        if (! $this->checkPermission((int) $id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        $players = array_map(function (Player $player) {
            return $player->jsonSerialize(true);
        }, $this->watchlist->getRedFlagList((int) $id));

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
        if (! $this->checkPermission((int) $id, $userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlist->getBlacklist((int) $id));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/exemption/list",
     *     operationId="watchlistExemptionList",
     *     summary="List of exempt players.",
     *     description="Needs role: watchlist, watchlist-admin",
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
        if (! $this->checkPermission((int) $id, $userAuth, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlist->getList((int) $id, 'exemption'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/exemption/add/{player}",
     *     operationId="watchlistExemptionAdd",
     *     summary="Add player to exemption list.",
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
    public function exemptionAdd(string $id, string $player): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'add', 'player', (int) $player);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/exemption/remove/{player}",
     *     operationId="watchlistExemptionRemove",
     *     summary="Remove player from exemption list.",
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
    public function exemptionRemove(string $id, string $player): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'remove', 'player', (int) $player);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/corporation/list",
     *     operationId="watchlistCorporationList",
     *     summary="List of corporations for this list.",
     *     description="Needs role: watchlist, watchlist-admin",
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
        if (! $this->checkPermission((int) $id, $userAuth, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlist->getList((int) $id, 'corporation'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/corporation/add/{corporation}",
     *     operationId="watchlistCorporationAdd",
     *     summary="Add corporation to the list.",
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
    public function corporationAdd(string $id, string $corporation): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'add', 'corporation', (int) $corporation);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/corporation/remove/{corporation}",
     *     operationId="watchlistCorporationRemove",
     *     summary="Remove corporation from the list.",
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
    public function corporationRemove(string $id, string $corporation): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'remove', 'corporation', (int) $corporation);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/alliance/list",
     *     operationId="watchlistAllianceList",
     *     summary="List of alliances for this list.",
     *     description="Needs role: watchlist, watchlist-admin",
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
        if (! $this->checkPermission((int) $id, $userAuth, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlist->getList((int) $id, 'alliance'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/alliance/add/{alliance}",
     *     operationId="watchlistAllianceAdd",
     *     summary="Add alliance to the list.",
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
    public function allianceAdd(string $id, string $alliance): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'add', 'alliance', (int) $alliance);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/alliance/remove/{alliance}",
     *     operationId="watchlistAllianceRemove",
     *     summary="Remove alliance from the list.",
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
    public function allianceRemove(string $id, string $alliance): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'remove', 'alliance', (int) $alliance);
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
        return $this->withJson($this->watchlist->getList((int) $id, 'group'));
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
        $response = $this->addOrRemoveEntity((int) $id, 'add', 'group', (int) $group);

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
        $response = $this->addOrRemoveEntity((int) $id, 'remove', 'group', (int) $group);

        if ($response->getStatusCode() === 204) {
            $account->syncWatchlistRole();
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
     *     description="Needs role: watchlist, watchlist-admin",
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
        if (! $this->checkPermission((int) $id, $userAuth, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlist->getList((int) $id, 'blacklistCorporations'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/blacklist-corporation/add/{corporation}",
     *     operationId="watchlistBlacklistCorporationAdd",
     *     summary="Add corporation to the blacklist.",
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
    public function blacklistCorporationAdd(string $id, string $corporation): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'add', 'blacklistCorporation', (int) $corporation);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/blacklist-corporation/remove/{corporation}",
     *     operationId="watchlistBlacklistCorporationRemove",
     *     summary="Remove corporation from the blacklist.",
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
    public function blacklistCorporationRemove(string $id, string $corporation): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'remove', 'blacklistCorporation', (int) $corporation);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/blacklist-alliance/list",
     *     operationId="watchlistBlacklistAllianceList",
     *     summary="List of alliances for the blacklist.",
     *     description="Needs role: watchlist, watchlist-admin",
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
        if (! $this->checkPermission((int) $id, $userAuth, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlist->getList((int) $id, 'blacklistAlliance'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/blacklist-alliance/add/{alliance}",
     *     operationId="watchlistBlacklistAllianceAdd",
     *     summary="Add alliance to the blacklist.",
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
    public function blacklistAllianceAdd(string $id, string $alliance): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'add', 'blacklistAlliance', (int) $alliance);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/blacklist-alliance/remove/{alliance}",
     *     operationId="watchlistBlacklistAllianceRemove",
     *     summary="Remove alliance from the blacklist.",
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
    public function blacklistAllianceRemove(string $id, string $alliance): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'remove', 'blacklistAlliance', (int) $alliance);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/whitelist-corporation/list",
     *     operationId="watchlistWhitelistCorporationList",
     *     summary="List of corporations for the corporation whitelist.",
     *     description="Needs role: watchlist, watchlist-admin",
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
        if (! $this->checkPermission((int) $id, $userAuth, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlist->getList((int) $id, 'whitelistCorporation'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/whitelist-corporation/add/{corporation}",
     *     operationId="watchlistWhitelistCorporationAdd",
     *     summary="Add corporation to the corporation whitelist.",
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
    public function whitelistCorporationAdd(string $id, string $corporation): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'add', 'whitelistCorporation', (int) $corporation);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/whitelist-corporation/remove/{corporation}",
     *     operationId="watchlistWhitelistCorporationRemove",
     *     summary="Remove corporation from the corporation whitelist.",
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
    public function whitelistCorporationRemove(string $id, string $corporation): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'remove', 'whitelistCorporation', (int) $corporation);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/whitelist-alliance/list",
     *     operationId="watchlistWhitelistAllianceList",
     *     summary="List of alliances for the alliance whitelist.",
     *     description="Needs role: watchlist, watchlist-admin",
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
        if (! $this->checkPermission((int) $id, $userAuth, true)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->watchlist->getList((int) $id, 'whitelistAlliance'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/whitelist-alliance/add/{alliance}",
     *     operationId="watchlistWhitelistAllianceAdd",
     *     summary="Add alliance to the alliance whitelist.",
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
    public function whitelistAllianceAdd(string $id, string $alliance): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'add', 'whitelistAlliance', (int) $alliance);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/{id}/whitelist-alliance/remove/{alliance}",
     *     operationId="watchlistWhitelistAllianceRemove",
     *     summary="Remove alliance from the alliance whitelist.",
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
    public function whitelistAllianceRemove(string $id, string $alliance): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'remove', 'whitelistAlliance', (int) $alliance);
    }

    /**
     * Checks if logged in user is member of a group that may see this watchlist.
     */
    private function checkPermission(int $id, UserAuth $userAuth, bool $adminFunction = false): bool
    {
        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($id);
        if ($watchlist === null) {
            return false;
        }

        $player = $this->getUser($userAuth)->getPlayer();

        if ($adminFunction && $player->hasRole(Role::WATCHLIST_ADMIN)) {
            return true;
        }

        $playerGroupIds = $player->getGroupIds();
        foreach ($watchlist->getGroups() as $group) {
            if (in_array($group->getId(), $playerGroupIds)) {
                return true;
            }
        }

        return false;
    }

    private function addOrRemoveEntity(int $id, string $action, string $type, int $entityId): ResponseInterface
    {
        $entity = null;
        if ($type === 'player') {
            $entity = $this->repositoryFactory->getPlayerRepository()->find($entityId);
        } elseif (in_array($type, ['corporation', 'blacklistCorporation', 'whitelistCorporation'])) {
            $entity = $this->repositoryFactory->getCorporationRepository()->find($entityId);
        } elseif (in_array($type, ['alliance', 'blacklistAlliance', 'whitelistAlliance'])) {
            $entity = $this->repositoryFactory->getAllianceRepository()->find($entityId);
        } elseif ($type === 'group') {
            $entity = $this->repositoryFactory->getGroupRepository()->find($entityId);
        }

        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($id);

        if ($entity === null || $watchlist === null) {
            return $this->response->withStatus(404);
        }

        if ($action === 'add') {
            if ($entity instanceof Player) {
                $watchlist->addExemption($entity);
            } elseif ($entity instanceof Corporation && $type === 'corporation') {
                $watchlist->addCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === 'alliance') {
                $watchlist->addAlliance($entity);
            } elseif ($entity instanceof Group) {
                $watchlist->addGroup($entity);
            } elseif ($entity instanceof Corporation && $type === 'blacklistCorporation') {
                $watchlist->addBlacklistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === 'blacklistAlliance') {
                $watchlist->addBlacklistAlliance($entity);
            } elseif ($entity instanceof Corporation && $type === 'whitelistCorporation') {
                $watchlist->addWhitelistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === 'whitelistAlliance') {
                $watchlist->addWhitelistAlliance($entity);
            }
        } elseif ($action === 'remove') {
            if ($entity instanceof Player) {
                $watchlist->removeExemption($entity);
            } elseif ($entity instanceof Corporation && $type === 'corporation') {
                $watchlist->removeCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === 'alliance') {
                $watchlist->removeAlliance($entity);
            } elseif ($entity instanceof Group) {
                $watchlist->removeGroup($entity);
            } elseif ($entity instanceof Corporation && $type === 'blacklistCorporation') {
                $watchlist->removeBlacklistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === 'blacklistAlliance') {
                $watchlist->removeBlacklistAlliance($entity);
            } elseif ($entity instanceof Corporation && $type === 'whitelistCorporation') {
                $watchlist->removeWhitelistCorporation($entity);
            } elseif ($entity instanceof Alliance && $type === 'whitelistAlliance') {
                $watchlist->removeWhitelistAlliance($entity);
            }
        }

        return $this->flushAndReturn(204);
    }
}
