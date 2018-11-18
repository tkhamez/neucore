<?php declare(strict_types=1);

use Brave\Core\Api\ApplicationController;
use Brave\Core\Api\User\AllianceController;
use Brave\Core\Api\User\AppController;
use Brave\Core\Api\User\AuthController;
use Brave\Core\Api\User\CharacterController;
use Brave\Core\Api\User\CorporationController;
use Brave\Core\Api\User\EsiController;
use Brave\Core\Api\User\GroupController;
use Brave\Core\Api\User\PlayerController;
use Brave\Core\Api\User\SettingsController;

return [
    '/api/app/v1/show'                     => ['GET',  ApplicationController::class.'::showV1'],
    '/api/app/v1/groups/{cid}'             => ['GET',  ApplicationController::class.'::groupsV1'],
    '/api/app/v2/groups/{cid}'             => ['GET',  ApplicationController::class.'::groupsV2'],
    '/api/app/v1/groups'                   => ['POST', ApplicationController::class.'::groupsBulkV1'],
    '/api/app/v1/corp-groups/{cid}'        => ['GET',  ApplicationController::class.'::corpGroupsV1'],
    '/api/app/v2/corp-groups/{cid}'        => ['GET',  ApplicationController::class.'::corpGroupsV2'],
    '/api/app/v1/corp-groups'              => ['POST', ApplicationController::class.'::corpGroupsBulkV1'],
    '/api/app/v1/alliance-groups/{aid}'    => ['GET',  ApplicationController::class.'::allianceGroupsV1'],
    '/api/app/v2/alliance-groups/{aid}'    => ['GET',  ApplicationController::class.'::allianceGroupsV2'],
    '/api/app/v1/alliance-groups'          => ['POST', ApplicationController::class.'::allianceGroupsBulkV1'],
    '/api/app/v1/main/{cid}'               => ['GET',  ApplicationController::class.'::mainV1'],
    '/api/app/v2/main/{cid}'               => ['GET',  ApplicationController::class.'::mainV2'],
    '/api/app/v1/characters/{characterId}' => ['GET',  ApplicationController::class.'::charactersV1'],

    '/api/user/app/all'                       => ['GET',    AppController::class.'::all'],
    '/api/user/app/create'                    => ['POST',   AppController::class.'::create'],
    '/api/user/app/{id}/rename'               => ['PUT',    AppController::class.'::rename'],
    '/api/user/app/{id}/delete'               => ['DELETE', AppController::class.'::delete'],
    '/api/user/app/{id}/groups'               => ['GET',    AppController::class.'::groups'],
    '/api/user/app/{id}/add-group/{gid}'      => ['PUT',    AppController::class.'::addGroup'],
    '/api/user/app/{id}/remove-group/{gid}'   => ['PUT',    AppController::class.'::removeGroup'],
    '/api/user/app/{id}/managers'             => ['GET',    AppController::class.'::managers'],
    '/api/user/app/{id}/add-manager/{pid}'    => ['PUT',    AppController::class.'::addManager'],
    '/api/user/app/{id}/remove-manager/{pid}' => ['PUT',    AppController::class.'::removeManager'],
    '/api/user/app/{id}/change-secret'        => ['PUT',    AppController::class.'::changeSecret'],

    '/api/user/alliance/all'                     => ['GET',  AllianceController::class.'::all'],
    '/api/user/alliance/with-groups'             => ['GET',  AllianceController::class.'::withGroups'],
    '/api/user/alliance/add/{id}'                => ['POST', AllianceController::class.'::add'],
    '/api/user/alliance/{id}/add-group/{gid}'    => ['PUT',  AllianceController::class.'::addGroup'],
    '/api/user/alliance/{id}/remove-group/{gid}' => ['PUT',  AllianceController::class.'::removeGroup'],

    '/api/user/auth/login-url'     => ['GET',  AuthController::class.'::loginUrl'],
    '/api/user/auth/callback'      => ['GET',  AuthController::class.'::callback'],
    '/api/user/auth/result'        => ['GET',  AuthController::class.'::result'],
    '/api/user/auth/logout'        => ['POST', AuthController::class.'::logout'],

    '/api/user/character/find-by/{name}'      => ['GET',  CharacterController::class.'::findBy'],
    '/api/user/character/find-player-of/{id}' => ['GET',  CharacterController::class.'::findPlayerOf'],
    '/api/user/character/show'                => ['GET',  CharacterController::class.'::show'],
    '/api/user/character/{id}/update'         => ['PUT',  CharacterController::class.'::update'],

    '/api/user/corporation/all'                     => ['GET',  CorporationController::class.'::all'],
    '/api/user/corporation/with-groups'             => ['GET',  CorporationController::class.'::withGroups'],
    '/api/user/corporation/add/{id}'                => ['POST', CorporationController::class.'::add'],
    '/api/user/corporation/{id}/add-group/{gid}'    => ['PUT',  CorporationController::class.'::addGroup'],
    '/api/user/corporation/{id}/remove-group/{gid}' => ['PUT',  CorporationController::class.'::removeGroup'],

    '/api/user/esi/request' => ['GET', EsiController::class.'::request'],

    '/api/user/group/all'                          => ['GET',    GroupController::class.'::all'],
    '/api/user/group/public'                       => ['GET',    GroupController::class.'::public'],
    '/api/user/group/create'                       => ['POST',   GroupController::class.'::create'],
    '/api/user/group/{id}/rename'                  => ['PUT',    GroupController::class.'::rename'],
    '/api/user/group/{id}/set-visibility/{choice}' => ['PUT',    GroupController::class.'::setVisibility'],
    '/api/user/group/{id}/delete'                  => ['DELETE', GroupController::class.'::delete'],
    '/api/user/group/{id}/managers'                => ['GET',    GroupController::class.'::managers'],
    '/api/user/group/{id}/corporations'            => ['GET',    GroupController::class.'::corporations'],
    '/api/user/group/{id}/alliances'               => ['GET',    GroupController::class.'::alliances'],
    '/api/user/group/{id}/add-manager/{pid}'       => ['PUT',    GroupController::class.'::addManager'],
    '/api/user/group/{id}/remove-manager/{pid}'    => ['PUT',    GroupController::class.'::removeManager'],
    '/api/user/group/{id}/applicants'              => ['GET',    GroupController::class.'::applicants'],
    '/api/user/group/{id}/remove-applicant/{pid}'  => ['PUT',    GroupController::class.'::removeApplicant'],
    '/api/user/group/{id}/add-member/{pid}'        => ['PUT',    GroupController::class.'::addMember'],
    '/api/user/group/{id}/remove-member/{pid}'     => ['PUT',    GroupController::class.'::removeMember'],
    '/api/user/group/{id}/members'                 => ['GET',    GroupController::class.'::members'],

    '/api/user/player/all'                      => ['GET',    PlayerController::class.'::all'],
    '/api/user/player/show'                     => ['GET',    PlayerController::class.'::show'],
    '/api/user/player/add-application/{gid}'    => ['PUT',    PlayerController::class.'::addApplication'],
    '/api/user/player/remove-application/{gid}' => ['PUT',    PlayerController::class.'::removeApplication'],
    '/api/user/player/leave-group/{gid}'        => ['PUT',    PlayerController::class.'::leaveGroup'],
    '/api/user/player/set-main/{cid}'           => ['PUT',    PlayerController::class.'::setMain'],
    '/api/user/player/delete-character/{id}'    => ['DELETE', PlayerController::class.'::deleteCharacter'],
    '/api/user/player/app-managers'             => ['GET',    PlayerController::class.'::appManagers'],
    '/api/user/player/group-managers'           => ['GET',    PlayerController::class.'::groupManagers'],
    '/api/user/player/{id}/add-role/{name}'     => ['PUT',    PlayerController::class.'::addRole'],
    '/api/user/player/{id}/remove-role/{name}'  => ['PUT',    PlayerController::class.'::removeRole'],
    '/api/user/player/{id}/show'                => ['GET',    PlayerController::class.'::showById'],
    '/api/user/player/{id}/characters'          => ['GET',    PlayerController::class.'::characters'],
    '/api/user/player/with-role/{name}'         => ['GET',    PlayerController::class.'::withRole'],

    '/api/user/settings/system/list'                       => ['GET',  SettingsController::class.'::systemList'],
    '/api/user/settings/system/change/{name}'              => ['PUT',  SettingsController::class.'::systemChange'],
    '/api/user/settings/system/send-account-disabled-mail' => ['POST', SettingsController::class.'::sendAccountDisabledMail'],
];
