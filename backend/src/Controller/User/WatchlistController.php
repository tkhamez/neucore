<?php
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Psr\Http\Message\ResponseInterface;

/**
 * @OA\Tag(
 *     name="Watchlist"
 * )
 */
class WatchlistController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/user/watchlist/players",
     *     operationId="watchlistPlayers",
     *     summary="List of players on this list.",
     *     description="Needs role: watchlist",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of players.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     )
     * )
     */
    public function players(): ResponseInterface
    {
        # TODO
        return $this->response;
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/exemption/list",
     *     operationId="watchlistExemptionList",
     *     summary="List of exempt players.",
     *     description="Needs role: watchlist",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of players.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Player"))
     *     )
     * )
     */
    public function exemptionList(): ResponseInterface
    {
        # TODO
        return $this->response;
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/exemption/add",
     *     operationId="watchlistExemptionAdd",
     *     summary="Add player to exemption list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="204",
     *         description="Player added."
     *     )
     * )
     */
    public function exemptionAdd(): ResponseInterface
    {
        # TODO
        return $this->response->withStatus(204);
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/exemption/remove",
     *     operationId="watchlistExemptionRemove",
     *     summary="Remove player from exemption list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="204",
     *         description="Player removed."
     *     )
     * )
     */
    public function exemptionRemove(): ResponseInterface
    {
        # TODO
        return $this->response->withStatus(204);
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/corporation/list",
     *     operationId="watchlistCorporationList",
     *     summary="List of corporations for this list.",
     *     description="Needs role: watchlist",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of corporation.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Corporation"))
     *     )
     * )
     */
    public function corporationList(): ResponseInterface
    {
        # TODO
        return $this->response;
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/corporation/add",
     *     operationId="watchlistCorporationAdd",
     *     summary="Add corporation to the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="204",
     *         description="Corporation added."
     *     )
     * )
     */
    public function corporationAdd(): ResponseInterface
    {
        # TODO
        return $this->response->withStatus(204);
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/corporation/remove",
     *     operationId="watchlistCorporationRemove",
     *     summary="Remove corporation from the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="204",
     *         description="Corporation removed."
     *     )
     * )
     */
    public function corporationRemove(): ResponseInterface
    {
        # TODO
        return $this->response->withStatus(204);
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/alliance/list",
     *     operationId="watchlistAllianceList",
     *     summary="List of alliances for this list.",
     *     description="Needs role: watchlist",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of alliances.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Alliance"))
     *     )
     * )
     */
    public function allianceList(): ResponseInterface
    {
        # TODO
        return $this->response;
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/alliance/add",
     *     operationId="watchlistAllianceAdd",
     *     summary="Add alliance to the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="204",
     *         description="Alliance added."
     *     )
     * )
     */
    public function allianceAdd(): ResponseInterface
    {
        # TODO
        return $this->response->withStatus(204);
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/alliance/remove",
     *     operationId="watchlistAllianceRemove",
     *     summary="Remove alliance from the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="204",
     *         description="Alliance removed."
     *     )
     * )
     */
    public function allianceRemove(): ResponseInterface
    {
        # TODO
        return $this->response->withStatus(204);
    }

    /**
     * @OA\Get(
     *     path="/user/watchlist/group/list",
     *     operationId="watchlistGroupList",
     *     summary="List of groups with access to this list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of groups.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Group"))
     *     )
     * )
     */
    public function groupList(): ResponseInterface
    {
        # TODO
        return $this->response;
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/group/add",
     *     operationId="watchlistGroupAdd",
     *     summary="Add access group to the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="204",
     *         description="Group added."
     *     )
     * )
     */
    public function groupAdd(): ResponseInterface
    {
        # TODO
        return $this->response->withStatus(204);
    }

    /**
     * @OA\Put(
     *     path="/user/watchlist/group/remove",
     *     operationId="watchlistGroupRemove",
     *     summary="Remove access group from the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="204",
     *         description="Group removed."
     *     )
     * )
     */
    public function groupRemove(): ResponseInterface
    {
        # TODO
        return $this->response->withStatus(204);
    }
}
