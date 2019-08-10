<?php declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Service\AppAuth;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;

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
class ApplicationController
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var AppAuth
     */
    private $appAuthService;

    public function __construct(Response $response, AppAuth $appAuthService)
    {
        $this->response = $response;
        $this->appAuthService = $appAuthService;
    }

    /**
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
    public function showV1(ServerRequestInterface $request): Response
    {
        return $this->response->withJson($this->appAuthService->getApp($request));
    }
}
