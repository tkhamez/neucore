<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Entity\Alliance;
use Brave\Core\Repository\AllianceRepository;
use Brave\Core\Repository\GroupRepository;
use Brave\Core\Service\EsiCharacter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Response;

/**
 * @SWG\Tag(
 *     name="Alliance",
 *     description="Alliance management (for automatic group assignment)."
 * )
 */
class AllianceController
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var AllianceRepository
     */
    private $allianceRepo;

    /**
     * @var GroupRepository
     */
    private $groupRepo;

    /**
     * @var Alliance
     */
    private $alliance;

    /**
     * @var \Brave\Core\Entity\Group
     */
    private $group;

    public function __construct(
        Response $response,
        LoggerInterface $log,
        EntityManagerInterface $em,
        AllianceRepository $allianceRepo,
        GroupRepository $groupRepo)
    {
        $this->response = $response;
        $this->log = $log;
        $this->em = $em;
        $this->allianceRepo = $allianceRepo;
        $this->groupRepo = $groupRepo;
    }

    /**
     * @SWG\Get(
     *     path="/user/alliance/all",
     *     operationId="all",
     *     summary="List all alliances.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of alliances.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Alliance"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function all(): Response
    {
        return $this->response->withJson($this->allianceRepo->findBy([], ['name' => 'ASC']));
    }

    /**
     * @SWG\Get(
     *     path="/user/alliance/with-groups",
     *     operationId="withGroups",
     *     summary="List all alliances that have groups assigned.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of alliances (this one includes the groups property).",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Alliance"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function withGroups(): Response
    {
        $result = [];
        foreach ($this->allianceRepo->getAllWithGroups() as $alliance) {
            // alliance model with groups
            $json = $alliance->jsonSerialize();
            $json['groups'] = $alliance->getGroups();
            $result[] = $json;
        }

        return $this->response->withJson($result);
    }

    /**
     * @SWG\Post(
     *     path="/user/alliance/add/{id}",
     *     operationId="add",
     *     summary="Add an EVE alliance to the database.",
     *     description="Needs role: group-admin
     *                  This makes an ESI request and adds the alliance only if it exists",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE alliance ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="201",
     *         description="The new alliance.",
     *         @SWG\Schema(ref="#/definitions/Alliance")
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Inalid alliance ID."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Alliance not found."
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="The alliance already exists."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @SWG\Response(
     *         response="503",
     *         description="ESI request failed."
     *     )
     * )
     */
    public function add(string $id, EsiCharacter $service): Response
    {
        $allianceId = (int) $id;

        if ($this->allianceRepo->find($allianceId)) {
            return $this->response->withStatus(409);
        }

        // get alliance
        $alliance = $service->fetchAlliance($allianceId, false);
        if ($alliance === null) {
            $code = $service->getEsiApi()->getLastErrorCode();
            if ($code === 404 || $code === 400) {
                return $this->response->withStatus($code);
            } else {
                return $this->response->withStatus(503);
            }
        }

        if (! $this->flush()) {
            return $this->response->withStatus(500);
        }

        return $this->response->withStatus(201)->withJson($alliance);
    }

    /**
     * @SWG\Put(
     *     path="/user/alliance/{id}/add-group/{gid}",
     *     operationId="addGroup",
     *     summary="Add a group to the alliance.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the alliance.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="gid",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Group added."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Alliance and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function addGroup(string $id, string $gid): Response
    {
        if (! $this->findAllianceAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        if (! $this->alliance->hasGroup($this->group->getId())) {
            $this->alliance->addGroup($this->group);
        }

        if (! $this->flush()) {
            return $this->response->withStatus(500);
        }

        return $this->response->withStatus(204);
    }

    /**
     * @SWG\Put(
     *     path="/user/alliance/{id}/remove-group/{gid}",
     *     operationId="removeGroup",
     *     summary="Remove a group from the alliance.",
     *     description="Needs role: group-admin",
     *     tags={"Alliance"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the alliance.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="gid",
     *         in="path",
     *         required=true,
     *         description="ID of the group.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Group removed."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Alliance and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function removeGroup(string $id, string $gid): Response
    {
        if (! $this->findAllianceAndGroup($id, $gid)) {
            return $this->response->withStatus(404);
        }

        $this->alliance->removeGroup($this->group);

        if (! $this->flush()) {
            return $this->response->withStatus(500);
        }

        return $this->response->withStatus(204);
    }

    private function findAllianceAndGroup(string $allianceId, string $groupId): bool
    {
        $this->alliance = $this->allianceRepo->find((int) $allianceId);
        $this->group = $this->groupRepo->find((int) $groupId);

        if ($this->alliance === null || $this->group === null) {
            return false;
        }

        return true;
    }

    private function flush(): bool
    {
        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return false;
        }

        return true;
    }
}
