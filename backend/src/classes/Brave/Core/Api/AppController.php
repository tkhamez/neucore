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
     *     path="/app/info",
     *     operationId="info",
     *     summary="Show app information. Needs role: app",
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
    public function info(ServerRequestInterface $request, Response $response, AppAuthService $aap)
    {
        return $response->withJson($aap->getApp($request));
    }
}
