<?php

use Brave\Core\Api\App\InfoController as AppInfoController;
use Brave\Core\Api\User\AuthController;
use Brave\Core\Api\User\InfoController as UserInfoController;

return [

    '/api/user/auth/login' =>       ['GET', AuthController::class.'::login',    'api_user_auth_login'],
    '/api/user/auth/callback' =>    ['GET', AuthController::class.'::callback', 'api_user_auth_callback'],
    '/api/user/auth/result' =>      ['GET', AuthController::class.'::result',   'api_user_auth_result'],
    '/api/user/auth/logout' =>      ['GET', AuthController::class.'::logout',   'api_user_auth_logout'],

    '/api/user/info' => ['GET', UserInfoController::class, 'api_user_info'],

    '/api/app/info' => ['GET', AppInfoController::class, 'api_app_info'],
];
