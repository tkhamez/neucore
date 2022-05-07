<?php

declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Controller\BaseController;
use Neucore\Service\AppAuth;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="Application",
 *     description="API for applications.",
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="BearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     description="The API key is a base64-encoded string containing the app ID and secret separated by a colon"
 * )
 */
class ApplicationController extends BaseController
{
    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/app/v1/show",
     *     operationId="showV1",
     *     summary="Show app information.",
     *     description="Needs role: app",
     *     tags={"Application"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="The app information",
     *         @OA\JsonContent(ref="#/components/schemas/App")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function showV1(ServerRequestInterface $request, AppAuth $appAuthService): ResponseInterface
    {
        return $this->withJson($appAuthService->getApp($request));
    }
}
