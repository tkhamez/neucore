<?php

use Brave\Core\Api\AppController;
use Brave\Core\Api\User\AuthController;
use Brave\Core\Api\User\GroupController;
use Brave\Core\Api\User\PlayerController;

return [

    '/api/user/auth/login-url'     => ['GET',  AuthController::class.'::loginUrl'],
    '/api/user/auth/login-alt-url' => ['GET',  AuthController::class.'::loginAltUrl'],
    '/api/user/auth/callback'      => ['GET',  AuthController::class.'::callback'],
    '/api/user/auth/result'        => ['GET',  AuthController::class.'::result'],
    '/api/user/auth/character'     => ['GET',  AuthController::class.'::character'],
    '/api/user/auth/player'        => ['GET',  AuthController::class.'::player'],
    '/api/user/auth/logout'        => ['POST', AuthController::class.'::logout'],

    '/api/user/group/all'                 => ['GET',    GroupController::class.'::all'],
    '/api/user/group/create'              => ['POST',   GroupController::class.'::create'],
    '/api/user/group/{id}/rename'         => ['PUT',    GroupController::class.'::rename'],
    '/api/user/group/{id}/delete'         => ['DELETE', GroupController::class.'::delete'],
    '/api/user/group/{id}/managers'       => ['GET',    GroupController::class.'::managers'],
    '/api/user/group/{id}/add-manager'    => ['PUT',    GroupController::class.'::addManager'],
    '/api/user/group/{id}/remove-manager' => ['PUT',    GroupController::class.'::removeManager'],

    '/api/user/player/all'              => ['GET', PlayerController::class.'::all'],
    '/api/user/player/group-managers'   => ['GET', PlayerController::class.'::groupManagers'],
    '/api/user/player/{id}/roles'       => ['GET', PlayerController::class.'::roles'],
    '/api/user/player/{id}/add-role'    => ['PUT', PlayerController::class.'::addRole'],
    '/api/user/player/{id}/remove-role' => ['PUT', PlayerController::class.'::removeRole'],

    '/api/app/info' => ['GET', AppController::class.'::info'],
];
