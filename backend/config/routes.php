<?php

use Brave\Core\Api\AppController;
use Brave\Core\Api\User\ApplicationController;
use Brave\Core\Api\User\AuthController;
use Brave\Core\Api\User\GroupController;
use Brave\Core\Api\User\PlayerController;

return [

    '/api/user/application/all'                          => ['GET',    ApplicationController::class.'::all'],
    '/api/user/application/create'                       => ['POST',   ApplicationController::class.'::create'],
    '/api/user/application/{id}/rename'                  => ['PUT',    ApplicationController::class.'::rename'],
    '/api/user/application/{id}/delete'                  => ['DELETE', ApplicationController::class.'::delete'],
    '/api/user/application/{id}/groups'                  => ['GET',    ApplicationController::class.'::groups'],
    '/api/user/application/{id}/add-group/{gid}'         => ['PUT',    ApplicationController::class.'::addGroup'],
    '/api/user/application/{id}/remove-group/{gid}'      => ['PUT',    ApplicationController::class.'::removeGroup'],
    '/api/user/application/{id}/managers'                => ['GET',    ApplicationController::class.'::managers'],
    '/api/user/application/{id}/add-manager/{player}'    => ['PUT',    ApplicationController::class.'::addManager'],
    '/api/user/application/{id}/remove-manager/{player}' => ['PUT',    ApplicationController::class.'::removeManager'],
    '/api/user/application/{id}/change-secret'           => ['PUT',    ApplicationController::class.'::changeSecret'],

    '/api/user/auth/login-url'     => ['GET',  AuthController::class.'::loginUrl'],
    '/api/user/auth/login-alt-url' => ['GET',  AuthController::class.'::loginAltUrl'],
    '/api/user/auth/callback'      => ['GET',  AuthController::class.'::callback'],
    '/api/user/auth/result'        => ['GET',  AuthController::class.'::result'],
    '/api/user/auth/character'     => ['GET',  AuthController::class.'::character'],
    '/api/user/auth/player'        => ['GET',  AuthController::class.'::player'],
    '/api/user/auth/logout'        => ['POST', AuthController::class.'::logout'],

    '/api/user/group/all'                          => ['GET',    GroupController::class.'::all'],
    '/api/user/group/create'                       => ['POST',   GroupController::class.'::create'],
    '/api/user/group/{id}/rename'                  => ['PUT',    GroupController::class.'::rename'],
    '/api/user/group/{id}/delete'                  => ['DELETE', GroupController::class.'::delete'],
    '/api/user/group/{id}/managers'                => ['GET',    GroupController::class.'::managers'],
    '/api/user/group/{id}/add-manager/{player}'    => ['PUT',    GroupController::class.'::addManager'],
    '/api/user/group/{id}/remove-manager/{player}' => ['PUT',    GroupController::class.'::removeManager'],

    '/api/user/player/all'                     => ['GET', PlayerController::class.'::all'],
    '/api/user/player/app-managers'            => ['GET', PlayerController::class.'::appManagers'],
    '/api/user/player/group-managers'          => ['GET', PlayerController::class.'::groupManagers'],
    '/api/user/player/{id}/roles'              => ['GET', PlayerController::class.'::roles'],
    '/api/user/player/{id}/add-role/{name}'    => ['PUT', PlayerController::class.'::addRole'],
    '/api/user/player/{id}/remove-role/{name}' => ['PUT', PlayerController::class.'::removeRole'],

    '/api/app/info/v1' => ['GET', AppController::class.'::infoV1'],
];
