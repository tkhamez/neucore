<?php
namespace Brave\Core\Api;

use Brave\Core\Service\AppAuthService;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;

/**
 *
 * @SWG\Tag(
 *     name="App",
 *     description="API for 3rd party apps.",
 * )
 */
class AppController
{

    /**
     * @SWG\Get(
     *     path="/app/info/v1",
     *     operationId="infoV1",
     *     deprecated=true,
     *     summary="Show app information.",
     *     description="Needs role: app",
     *     tags={"App"},
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
    public function infoV1(ServerRequestInterface $request, Response $response, AppAuthService $aap)
    {
        return $response->withJson($aap->getApp($request));
    }

    /**
     * @SWG\Get(
     *     path="/app/info/v2",
     *     operationId="infoV2",
     *     summary="Show app information.",
     *     description="Alias route: /app/info<br>Needs role: app",
     *     tags={"App"},
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
    public function infoV2(ServerRequestInterface $request, Response $response, AppAuthService $aap)
    {
        return $response->withJson($aap->getApp($request));
    }
}
