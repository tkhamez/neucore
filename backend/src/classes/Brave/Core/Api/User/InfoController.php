<?php
namespace Brave\Core\Api\User;

use Brave\Core\Service\EveService;
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
     *         description="The player information",
     *         @SWG\Schema(ref="#/definitions/Player")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="If not authorized"
     *     )
     * )
     */
    public function __invoke(Response $response, UserAuthService $uas, EveService $es)
    {
        $char = $uas->getUser();

        // example
//         $apiInstance = new \Swagger\Client\Api\CharacterApi(null, $es->getConfiguration());
//         try {
//             $result = $apiInstance->getCharactersCharacterId($char->getId());
//             print_r($result);
//         } catch (\Exception $e) {
//             echo 'Exception when calling CharacterApi->getCharactersCharacterId: ', $e->getMessage(), PHP_EOL;
//         }


        return $response->withJson($char->getPlayer());
    }
}
