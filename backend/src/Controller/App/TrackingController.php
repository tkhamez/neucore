<?php

declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Controller\BaseController;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="Application - Tracking"
 * )
 */
class TrackingController extends BaseController
{
    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/app/v1/corporation/{id}/member-tracking",
     *     operationId="memberTrackingV1",
     *     summary="Return corporation member tracking data.",
     *     description="Needs role: app-tracking",
     *     tags={"Application - Tracking"},
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
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     )
     * )
     */
    public function memberTrackingV1(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $inactive = $this->getQueryParam($request, 'inactive');
        $active = $this->getQueryParam($request, 'active');
        $accountParam = $this->getQueryParam($request, 'account');
        if ($accountParam === 'true') {
            $account = true;
        } else {
            $account = $accountParam === 'false' ? false : null;
        }

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

        return $this->withJson($result);
    }
}
