<?php
namespace Brave\Core\Api\User\Controller;

use Slim\Http\Response;
use Brave\Core\Service\UserAuthService;

class UserInfo
{

    public function __invoke(Response $response, UserAuthService $uas)
    {
        return $response->withJson($uas->getUser());
    }
}
