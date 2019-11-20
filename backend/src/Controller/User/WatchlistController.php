<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Service\UserAuth;
use Psr\Http\Message\ResponseInterface;

/**
 * Watchlist controller.
 *
 * There is only one watchlist atm. (ID 1), which is hard coded here for now.
 *
 * @OA\Tag(
 *     name="Watchlist"
 * )
 */
class WatchlistController extends BaseController
{
    /**
     * @var int
     */
    private $id = 1;

    /**
     * @noinspection PhpUnused
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
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function players(UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission($userAuth)) {
            return $this->response->withStatus(403);
        }

        $allianceIds = array_map(function (Alliance $alliance) {
            return $alliance->getId();
        }, $this->getList('alliance'));

        $corporationIds1 = array_map(function (Corporation $corporation) {
            return $corporation->getId();
        }, $this->repositoryFactory->getCorporationRepository()->getAllFromAlliances($allianceIds));
        $corporationIds2 = array_map(function (Corporation $corporation) {
            return $corporation->getId();
        }, $this->getList('corporation'));
        $corporationIds = array_unique(array_merge($corporationIds1, $corporationIds2));

        $exemptPlayers = array_map(function (array $player) {
            return $player['id'];
        }, $this->getList('exemption'));

        $playerRepository = $this->repositoryFactory->getPlayerRepository();

        $players1 = $playerRepository->findInCorporationsWithExcludes($corporationIds, $exemptPlayers);
        $players2 = $playerRepository->findNotInNpcCorporationsWithExcludes($corporationIds, $exemptPlayers);

        $player2Ids = array_map(function (Player $player) {
            return $player->getId();
        }, $players2);

        $result = [];
        foreach ($players1 as $player1) {
            if (in_array($player1->getId(), $player2Ids)) {
                $result[] = $player1->jsonSerialize(true);
            }
        }

        return $this->withJson($result);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/exemption/list",
     *     operationId="watchlistExemptionList",
     *     summary="List of exempt players.",
     *     description="Needs role: watchlist",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
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
    public function exemptionList(UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission($userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->getList('exemption'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/exemption/add/{id}",
     *     operationId="watchlistExemptionAdd",
     *     summary="Add player to exemption list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
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
    public function exemptionAdd(string $id): ResponseInterface
    {
        return $this->addOrRemoveEntity('add', 'player', (int) $id);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/exemption/remove/{id}",
     *     operationId="watchlistExemptionRemove",
     *     summary="Remove player from exemption list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
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
    public function exemptionRemove(string $id): ResponseInterface
    {
        return $this->addOrRemoveEntity('remove', 'player', (int) $id);
    }

    /**
     * @noinspection PhpUnused
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
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corporationList(UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission($userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->getList('corporation'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/corporation/add/{id}",
     *     operationId="watchlistCorporationAdd",
     *     summary="Add corporation to the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
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
    public function corporationAdd(string $id): ResponseInterface
    {
        return $this->addOrRemoveEntity('add', 'corporation', (int) $id);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/corporation/remove/{id}",
     *     operationId="watchlistCorporationRemove",
     *     summary="Remove corporation from the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
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
    public function corporationRemove(string $id): ResponseInterface
    {
        return $this->addOrRemoveEntity('remove', 'corporation', (int) $id);
    }

    /**
     * @noinspection PhpUnused
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
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allianceList(UserAuth $userAuth): ResponseInterface
    {
        if (! $this->checkPermission($userAuth)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($this->getList('alliance'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/alliance/add/{id}",
     *     operationId="watchlistAllianceAdd",
     *     summary="Add alliance to the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
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
    public function allianceAdd(string $id): ResponseInterface
    {
        return $this->addOrRemoveEntity('add', 'alliance', (int) $id);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/alliance/remove/{id}",
     *     operationId="watchlistAllianceRemove",
     *     summary="Remove alliance from the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
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
    public function allianceRemove(string $id): ResponseInterface
    {
        return $this->addOrRemoveEntity('remove', 'alliance', (int) $id);
    }

    /**
     * @noinspection PhpUnused
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
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupList(): ResponseInterface
    {
        return $this->withJson($this->getList('group'));
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/group/add/{id}",
     *     operationId="watchlistGroupAdd",
     *     summary="Add access group to the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
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
    public function groupAdd(string $id): ResponseInterface
    {
        return $this->addOrRemoveEntity('add', 'group', (int) $id);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/watchlist/group/remove/{id}",
     *     operationId="watchlistGroupRemove",
     *     summary="Remove access group from the list.",
     *     description="Needs role: watchlist-admin",
     *     tags={"Watchlist"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
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
    public function groupRemove(string $id): ResponseInterface
    {
        return $this->addOrRemoveEntity('remove', 'group', (int) $id);
    }

    /**
     * Checks if logged in user is member of a group that may see this watchlist.
     */
    private function checkPermission(UserAuth $userAuth): bool
    {
        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($this->id);
        if ($watchlist === null) {
            return false;
        }

        $playerGroupIds = $this->getUser($userAuth)->getPlayer()->getGroupIds();
        foreach ($watchlist->getGroups() as $group) {
            if (in_array($group->getId(), $playerGroupIds)) {
                return true;
            }
        }

        return false;
    }

    private function getList(string $type): array
    {
        $data = [];
        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($this->id);

        if ($watchlist === null) {
            return $data;
        }

        if ($type === 'group') {
            $data = $watchlist->getGroups();
        } elseif ($type === 'alliance') {
            $data = $watchlist->getAlliances();
        } elseif ($type === 'corporation') {
            $data = $watchlist->getCorporations();
        } elseif ($type === 'exemption') {
            $data = array_map(function (Player $player) {
                return $player->jsonSerialize(true);
            }, $watchlist->getExemptions());
        }

        return $data;
    }

    private function addOrRemoveEntity(string $action, string $type, int $id): ResponseInterface
    {
        $entity = null;
        if ($type === 'player') {
            $entity = $this->repositoryFactory->getPlayerRepository()->find($id);
        } elseif ($type === 'corporation') {
            $entity = $this->repositoryFactory->getCorporationRepository()->find($id);
        } elseif ($type === 'alliance') {
            $entity = $this->repositoryFactory->getAllianceRepository()->find($id);
        } elseif ($type === 'group') {
            $entity = $this->repositoryFactory->getGroupRepository()->find($id);
        }

        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($this->id);

        if ($entity === null || $watchlist === null) {
            return $this->response->withStatus(404);
        }

        if ($action === 'add') {
            if ($entity instanceof Player) {
                $watchlist->addExemption($entity);
            } elseif ($entity instanceof Corporation) {
                $watchlist->addCorporation($entity);
            } elseif ($entity instanceof Alliance) {
                $watchlist->addAlliance($entity);
            } elseif ($entity instanceof Group) {
                $watchlist->addGroup($entity);
            }
        } elseif ($action === 'remove') {
            if ($entity instanceof Player) {
                $watchlist->removeExemption($entity);
            } elseif ($entity instanceof Corporation) {
                $watchlist->removeCorporation($entity);
            } elseif ($entity instanceof Alliance) {
                $watchlist->removeAlliance($entity);
            } elseif ($entity instanceof Group) {
                $watchlist->removeGroup($entity);
            }
        }

        return $this->flushAndReturn(204);
    }
}
