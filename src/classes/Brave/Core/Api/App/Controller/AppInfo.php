<?php
namespace Brave\Core\Api\App\Controller;

use Brave\Core\Service\AppAuthService;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Response;

class AppInfo
{

    public function __invoke(ServerRequestInterface $request, Response $response, AppAuthService $aap)
    {
        return $response->withJson($aap->getApp($request));
    }
}
