<?php
namespace Brave\Core\Api\User;

use Brave\Core\Service\UserAuthService;
use Slim\Http\Response;

class InfoController
{

    /**
     * @SWG\Get(
     *     path="/user/info",
     *     summary="Show current logged in player information. Needs role: user",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The player information.",
     *         @SWG\Schema(ref="#/definitions/Player")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function __invoke(Response $response, UserAuthService $uas)
    {
        return $response->withJson($uas->getUser()->getPlayer());
    }
}
