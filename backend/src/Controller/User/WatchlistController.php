<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Alliance;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Service\UserAuth;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * @OA\Tag(
 *     name="Watchlist"
 * )
 */
class WatchlistController extends BaseController
{
    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/watchlist/{id}/players",
     *     operationId="watchlistPlayers",
     *     summary="List of player accounts that have characters in one of the configured alliances or corporations
                    and additionally have other characters in another player (not NPC) corporation and have not
                    been manually excluded.",
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

        $allianceIds = array_map(function (Alliance $alliance) {
            return $alliance->getId();
        }, $this->getList((int) $id, 'alliance'));

        $corporationIds1 = array_map(function (Corporation $corporation) {
            return $corporation->getId();
        }, $this->repositoryFactory->getCorporationRepository()->getAllFromAlliances($allianceIds));
        $corporationIds2 = array_map(function (Corporation $corporation) {
            return $corporation->getId();
        }, $this->getList((int) $id, 'corporation'));
        $corporationIds = array_unique(array_merge($corporationIds1, $corporationIds2));

        $exemptPlayers = array_map(function (array $player) {
            return $player['id'];
        }, $this->getList((int) $id, 'exemption'));

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
     *     path="/user/watchlist/{id}/exemption/list",
     *     operationId="watchlistExemptionList",
     *     summary="List of exempt players.",
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

        return $this->withJson($this->getList((int) $id, 'exemption'));
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

        return $this->withJson($this->getList((int) $id, 'corporation'));
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

        return $this->withJson($this->getList((int) $id, 'alliance'));
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
        return $this->withJson($this->getList((int) $id, 'group'));
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
    public function groupAdd(string $id, string $group): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'add', 'group', (int) $group);

        # TODO add WATCHLIST role to users
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
    public function groupRemove(string $id, string $group): ResponseInterface
    {
        return $this->addOrRemoveEntity((int) $id, 'remove', 'group', (int) $group);

        # TODO remove WATCHLIST role from users
    }

    /**
     * Checks if logged in user is member of a group that may see this watchlist.
     */
    private function checkPermission(int $id, UserAuth $userAuth): bool
    {
        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($id);
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

    private function getList(int $id, string $type): array
    {
        $data = [];
        $watchlist = $this->repositoryFactory->getWatchlistRepository()->find($id);

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

    private function addOrRemoveEntity(int $id, string $action, string $type, int $entityId): ResponseInterface
    {
        $entity = null;
        if ($type === 'player') {
            $entity = $this->repositoryFactory->getPlayerRepository()->find($entityId);
        } elseif ($type === 'corporation') {
            $entity = $this->repositoryFactory->getCorporationRepository()->find($entityId);
        } elseif ($type === 'alliance') {
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
