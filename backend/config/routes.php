<?php

use Brave\Core\Api\App\InfoController as AppInfoController;
use Brave\Core\Api\User\AuthController;
use Brave\Core\Api\User\GroupController;
use Brave\Core\Api\User\InfoController as UserInfoController;
use Brave\Core\Api\User\PlayerController;

return [

    '/api/user/auth/login'     => ['GET', AuthController::class . '::login'],
    '/api/user/auth/login-alt' => ['GET', AuthController::class . '::loginAlt'],
    '/api/user/auth/callback'  => ['GET', AuthController::class . '::callback'],
    '/api/user/auth/result'    => ['GET', AuthController::class . '::result'],
    '/api/user/auth/character' => ['GET', AuthController::class . '::character'],
    '/api/user/auth/logout'    => ['GET', AuthController::class . '::logout'],

    '/api/user/group/list'                => ['GET',    GroupController::class . '::list'],
    '/api/user/group/create'              => ['POST',   GroupController::class . '::create'],
    '/api/user/group/{id}/rename'         => ['PUT',    GroupController::class . '::rename'],
    '/api/user/group/{id}/delete'         => ['DELETE', GroupController::class . '::delete'],
    '/api/user/group/{id}/add-manager'    => ['PUT',    GroupController::class . '::addManager'],
    '/api/user/group/{id}/remove-manager' => ['PUT',    GroupController::class . '::removeManager'],

    '/api/user/player/list'               => ['GET', PlayerController::class . '::list'],
    '/api/user/player/list-group-manager' => ['GET', PlayerController::class . '::listGroupManager'],
    '/api/user/player/{id}/roles'         => ['GET', PlayerController::class . '::listRoles'],
    '/api/user/player/{id}/add-role'      => ['PUT', PlayerController::class . '::addRole'],
    '/api/user/player/{id}/remove-role'   => ['PUT', PlayerController::class . '::removeRole'],

    '/api/user/info' => ['GET', UserInfoController::class],

    '/api/app/info' => ['GET', AppInfoController::class],
];
