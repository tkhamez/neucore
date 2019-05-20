<?php declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Factory\RepositoryFactory;
use OpenApi\Annotations as OA;
use Slim\Http\Request;
use Slim\Http\Response;

class CorporationController
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    public function __construct(
        Response $response,
        RepositoryFactory $repositoryFactory
    ) {
        $this->response = $response;
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @OA\Get(
     *     path="/app/v1/corporation/{id}/member-tracking",
     *     operationId="memberTrackingV1",
     *     summary="Return corporation member tracking data.",
     *     description="Needs role: app-tracking",
     *     tags={"Application"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="inactive",
     *         in="query",
     *         description="Limit to members who have been inactive for x days or longer.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Limit to members who were active in the last x days.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="account",
     *         in="query",
     *         description="Limit to members with (true) or without (false) an account.",
     *         @OA\Schema(type="string", enum={"true", "false"})
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Members ordered by logonDate descending (character and player properties excluded).",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/CorporationMember"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function memberTrackingV1(string $id, Request $request)
    {
        $inactive = $request->getParam('inactive');
        $active = $request->getParam('active');
        $account = $request->getParam('account');
        $account = $account === 'true' ? true : ($account === 'false' ? false : null);

        $members = $this->repositoryFactory
            ->getCorporationMemberRepository()
            ->setInactive($inactive !== null ? (int) $inactive : null)
            ->setActive($active !== null ? (int) $active : null)
            ->setAccount($account)
            ->findMatching((int) $id);

        $result = [];
        foreach ($members as $member) {
            $result[] = $member->jsonSerialize(false);
        }

        return $this->response->withJson($result);
    }
}
