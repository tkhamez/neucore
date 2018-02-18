<?php

use Brave\Core\Api\App\InfoController as AppInfoController;
use Brave\Core\Api\User\AuthController;
use Brave\Core\Api\User\InfoController as UserInfoController;

return [

    '/api/user/auth/login' =>       ['GET', AuthController::class.'::login'],
    '/api/user/auth/callback' =>    ['GET', AuthController::class.'::callback'],
    '/api/user/auth/result' =>      ['GET', AuthController::class.'::result'],
    '/api/user/auth/logout' =>      ['GET', AuthController::class.'::logout'],

    '/api/user/info' => ['GET', UserInfoController::class],

    '/api/app/info' => ['GET', AppInfoController::class],
];
