<?php declare(strict_types=1);

namespace Brave\Core\Api\App;

use Brave\Core\Service\AppAuth;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;
use Swagger\Annotations as SWG;

/**
 * @SWG\Tag(
 *     name="Application",
 *     description="API for 3rd party apps.",
 * )
 *
 * @SWG\SecurityScheme(
 *     securityDefinition="Bearer",
 *     type="apiKey",
 *     name="Authorization",
 *     in="header",
 *     description="Example: Bearer ABC"
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
     * @SWG\Get(
     *     path="/app/v1/show",
     *     operationId="showV1",
     *     summary="Show app information.",
     *     description="Needs role: app",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The app information",
     *         @SWG\Schema(ref="#/definitions/App")
     *     ),
     *     @SWG\Response(
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
