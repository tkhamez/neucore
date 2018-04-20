<?php
namespace Brave\Core\Api;

use Brave\Core\Service\AppAuthService;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;

/**
 *
 * @SWG\Tag(
 *     name="Application",
 *     description="API for 3rd party apps.",
 * )
 */
class ApplicationController
{

    /**
     * @SWG\Get(
     *     path="/app/info/v1",
     *     operationId="infoV1",
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
    public function infoV1(ServerRequestInterface $request, Response $response, AppAuthService $aap)
    {
        return $response->withJson($aap->getApp($request));
    }
}
