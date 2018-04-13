<?php

use Brave\Core\Api\App\InfoController as AppInfoController;
use Brave\Core\Api\User\AuthController;
use Brave\Core\Api\User\GroupController;
use Brave\Core\Api\User\InfoController as UserInfoController;
use Brave\Core\Api\User\PlayerController;
use Brave\Core\Api\User\RoleController;

return [

    '/api/user/auth/login' =>       ['GET', AuthController::class . '::login'],
    '/api/user/auth/login-alt' =>   ['GET', AuthController::class . '::loginAlt'],
    '/api/user/auth/callback' =>    ['GET', AuthController::class . '::callback'],
    '/api/user/auth/result' =>      ['GET', AuthController::class . '::result'],
    '/api/user/auth/character' =>   ['GET', AuthController::class . '::character'],
    '/api/user/auth/logout' =>      ['GET', AuthController::class . '::logout'],

    '/api/user/player/list' =>      ['GET', PlayerController::class . '::list'],

    '/api/user/group/list' =>       ['GET',    GroupController::class . '::list'],
    '/api/user/group/create' =>     ['POST',   GroupController::class . '::create'],
    '/api/user/group/rename' =>     ['PUT',    GroupController::class . '::rename'],
    '/api/user/group/delete' =>     ['DELETE', GroupController::class . '::delete'],

    '/api/user/role/list-player' =>   ['GET', RoleController::class . '::listRolesOfPlayer'],
    '/api/user/role/add-player' =>    ['PUT', RoleController::class . '::addRoleToPlayer'],
    '/api/user/role/remove-player' => ['PUT', RoleController::class . '::removeRoleFromPlayer'],

    '/api/user/info' => ['GET', UserInfoController::class],

    '/api/app/info' =>  ['GET', AppInfoController::class],
];
