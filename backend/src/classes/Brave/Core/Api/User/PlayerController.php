<?php
namespace Brave\Core\Api\User;

use Brave\Core\Entity\PlayerRepository;
use Slim\Http\Response;

/**
 *
 * @SWG\Definition(
 *     definition="PlayerList",
 *     type="array",
 *     @SWG\Items(
 *         type="object",
 *         required={"id", "name"},
 *         @SWG\Property(property="id", type="integer"),
 *         @SWG\Property(property="name", type="string")
 *     )
 * )
 */
class PlayerController
{

    private $response;

    public function __construct(Response $res)
    {
        $this->response = $res;
    }

    /**
     * @SWG\Get(
     *     path="/user/player/list",
     *     summary="Lists all players. Needs role: user-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="Array of players, ordered by name.",
     *         @SWG\Schema(ref="#/definitions/PlayerList")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function list(PlayerRepository $pr)
    {
        $ret = [];

        foreach ($pr->findBy([], ['name' => 'ASC']) as $player) {
            $ret[] = [
                'id' => $player->getId(),
                'name' => $player->getName()
            ];
        }

        return $this->response->withJson($ret);
    }
}
