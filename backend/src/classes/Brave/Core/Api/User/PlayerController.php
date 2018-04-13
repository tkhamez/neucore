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
 *
 */
class PlayerController
{

    private $res;

    private $pr;

    public function __construct(Response $response, PlayerRepository $pr)
    {
        $this->res = $response;
        $this->pr = $pr;
    }

    /**
     * @SWG\Get(
     *     path="/user/player/list",
     *     summary="Lists all players. Needs role: user-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of players, ordered by name.",
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

        return $this->res->withJson($ret);
    }

    /**
     * @SWG\Get(
     *     path="/user/player/{id}/roles",
     *     summary="List all roles of one player. Needs role: user-admin",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of player.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of roles.",
     *         @SWG\Schema(ref="#/definitions/RoleList")
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="If player was not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function listRoles($id)
    {
        $player = $this->pr->find($id);

        if ($player) {
            return $this->res->withJson($player->getRoles());
        } else {
            return $this->res->withStatus(404);
        }
    }
}
