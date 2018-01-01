<?php
namespace Brave\Core\Api\App\Controller;

use Brave\Core\Service\AppAuthService;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;

class AppInfo
{

    /**
     * @SWG\Get(
     *     path="/app/info",
     *     summary="Show app information",
     *     tags={"App"},
     *     security={{"Bearer"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The app information",
     *         @SWG\Schema(ref="#/definitions/App")
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="If not authenticated"
     *     )
     * )
     */
    public function __invoke(ServerRequestInterface $request, Response $response, AppAuthService $aap)
    {
        return $response->withJson($aap->getApp($request));
    }
}
