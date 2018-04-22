<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Entity\Alliance;
use Brave\Core\Entity\AllianceRepository;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\CorporationRepository;
use Brave\Core\Entity\GroupRepository;
use Brave\Core\Service\EsiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Response;

/**
 * @SWG\Tag(
 *     name="Corporation",
 *     description="Corporation management (for automatic group assignment)."
 * )
 */
class CorporationController
{
    /**
     * @var Response
     */
    private $res;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var CorporationRepository
     */
    private $corpRepo;

    /**
     * @var GroupRepository
     */
    private $groupRepo;

    /**
     * @var AllianceRepository
     */
    private $alliRepo;

    /**
     * @var Corporation
     */
    private $corp;

    /**
     * @var \Brave\Core\Entity\Group
     */
    private $group;

    public function __construct(Response $response, LoggerInterface $log, EntityManagerInterface $em,
        CorporationRepository $corpRepo, AllianceRepository $alliRepo, GroupRepository $groupRepo)
    {
        $this->res = $response;
        $this->log = $log;
        $this->em = $em;
        $this->corpRepo = $corpRepo;
        $this->alliRepo = $alliRepo;
        $this->groupRepo = $groupRepo;
    }

    /**
     * @SWG\Get(
     *     path="/user/corporation/all",
     *     operationId="all",
     *     summary="List all corporations.",
     *     description="Needs role: user-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of corporations.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Corporation"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function all(): Response
    {
        return $this->res->withJson($this->corpRepo->findAll());
    }

    /**
     * @SWG\Get(
     *     path="/user/corporation/with-groups",
     *     operationId="withGroups",
     *     summary="List all corporations that have groups assigned.",
     *     description="Needs role: user-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of corporations (this one includes the groups property).",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Corporation"))
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
        foreach ($this->corpRepo->getAllWithGroups() as $corp) {
            // corporation model with groups
            $result[] = [
                'id' => $corp->getId(),
                'name' => $corp->getName(),
                'ticker' => $corp->getTicker(),
                'groups' => $corp->getGroups()
            ];
        }

        return $this->res->withJson($result);
    }

    /**
     * @SWG\Post(
     *     path="/user/corporation/add/{id}",
     *     operationId="add",
     *     summary="Add an EVE corporation to the database.",
     *     description="Needs role: user-admin
     *                  This makes an ESI request and adds the corporation only if it exists.
     *                  Also adds the corresponding alliance, if there is one.",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="201",
     *         description="The new corporation.",
     *         @SWG\Schema(ref="#/definitions/Corporation")
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Inalid corporation ID."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Corporation not found."
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="The corporation already exists."
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
    public function add(string $id, EsiService $es): Response
    {
        $corpId = (int) $id;

        if ($this->corpRepo->find($id)) {
            return $this->res->withStatus(409);
        }

        // get corporation from ESI
        $eveCorp = $es->getCorporation($corpId);
        if ($eveCorp === null) {
            $code = $es->getLastErrorCode();
            #var_Dump($es->getLastErrorMessage());
            if ($code === 404 || $code === 400) {
                return $this->res->withStatus($code);
            } else {
                return $this->res->withStatus(503);
            }
        }

        // find/create alliance
        $alliId = (int) $eveCorp->getAllianceId();
        $alliance = null;
        if ($alliId > 0) {
            $alliance = $this->alliRepo->find($alliId);
            if ($alliance === null) {
                $eveAlli = $es->getAlliance($alliId);
                if ($eveAlli === null) {
                    return $this->res->withStatus(503);
                }

                $alliance = new Alliance();
                $alliance->setId($alliId);
                $alliance->setName($eveAlli->getName());
                $alliance->setTicker($eveAlli->getTicker());
                $this->em->persist($alliance);
            }
        }

        // create corp
        $corporation = new Corporation();
        $corporation->setId($corpId);
        $corporation->setName($eveCorp->getName());
        $corporation->setTicker($eveCorp->getTicker());
        if ($alliance !== null) {
            $corporation->setAlliance($alliance);
            $alliance->addCorporation($corporation);
        }
        $this->em->persist($corporation);

        if (! $this->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(201)->withJson($corporation);
    }

    /**
     * @SWG\Put(
     *     path="/user/corporation/{id}/add-group/{gid}",
     *     operationId="addGroup",
     *     summary="Add a group to the corporation.",
     *     description="Needs role: user-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
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
     *         description="Corporation and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function addGroup(string $id, string $gid): Response
    {
        if (! $this->findCorpAndGroup($id, $gid)) {
            return $this->res->withStatus(404);
        }

        $add = true;
        foreach ($this->corp->getGroups() as $gp) {
            if ($gp->getId() === $this->group->getId()) {
                $add = false;
                break;
            }
        }

        if ($add) {
            $this->corp->addGroup($this->group);
        }

        if (! $this->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
    }

    /**
     * @SWG\Put(
     *     path="/user/corporation/{id}/remove-group/{gid}",
     *     operationId="removeGroup",
     *     summary="Remove a group from the corporation.",
     *     description="Needs role: user-admin",
     *     tags={"Corporation"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the corporation.",
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
     *         description="Corporation and/or group not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function removeGroup(string $id, string $gid): Response
    {
        if (! $this->findCorpAndGroup($id, $gid)) {
            return $this->res->withStatus(404);
        }

        $this->corp->removeGroup($this->group);

        if (! $this->flush()) {
            return $this->res->withStatus(500);
        }

        return $this->res->withStatus(204);
    }

    private function findCorpAndGroup(string $corpId, string $groupId): bool
    {
        $this->corp = $this->corpRepo->find((int) $corpId);
        $this->group = $this->groupRepo->find((int) $groupId);

        if ($this->corp === null || $this->group === null) {
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
