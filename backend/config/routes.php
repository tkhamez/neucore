<?php declare(strict_types=1);

use Neucore\Controller\App\ApplicationController;
use Neucore\Controller\App\CharController;
use Neucore\Controller\App\CorporationController as AppCorporationController;
use Neucore\Controller\App\EsiController as AppEsiController;
use Neucore\Controller\App\GroupController as AppGroupController;
use Neucore\Controller\User\AllianceController;
use Neucore\Controller\User\AppController;
use Neucore\Controller\User\AuthController;
use Neucore\Controller\User\CharacterController;
use Neucore\Controller\User\CorporationController;
use Neucore\Controller\User\EsiController;
use Neucore\Controller\User\GroupController;
use Neucore\Controller\User\PlayerController;
use Neucore\Controller\User\SettingsController;
use Neucore\Controller\User\WatchlistController;

return [

    '/login'             => ['GET',  AuthController::class.'::login'],
    '/login-managed'     => ['GET',  AuthController::class.'::loginManaged'],
    '/login-managed-alt' => ['GET',  AuthController::class.'::loginManagedAlt'],
    '/login-alt'         => ['GET',  AuthController::class.'::loginAlt'],
    '/login-mail'        => ['GET',  AuthController::class.'::loginMail'],
    '/login-director'    => ['GET',  AuthController::class.'::loginDirector'],
    '/login-callback'    => ['GET',  AuthController::class.'::callback'],

    '/api/app/v1/show'                               => ['GET',  ApplicationController::class.'::showV1'],
    '/api/app/v1/groups/{cid}'                       => ['GET',  AppGroupController::class.'::groupsV1'],
    '/api/app/v2/groups/{cid}'                       => ['GET',  AppGroupController::class.'::groupsV2'],
    '/api/app/v1/groups'                             => ['POST', AppGroupController::class.'::groupsBulkV1'],
    '/api/app/v1/corp-groups/{cid}'                  => ['GET',  AppGroupController::class.'::corpGroupsV1'],
    '/api/app/v2/corp-groups/{cid}'                  => ['GET',  AppGroupController::class.'::corpGroupsV2'],
    '/api/app/v1/corp-groups'                        => ['POST', AppGroupController::class.'::corpGroupsBulkV1'],
    '/api/app/v1/alliance-groups/{aid}'              => ['GET',  AppGroupController::class.'::allianceGroupsV1'],
    '/api/app/v2/alliance-groups/{aid}'              => ['GET',  AppGroupController::class.'::allianceGroupsV2'],
    '/api/app/v1/alliance-groups'                    => ['POST', AppGroupController::class.'::allianceGroupsBulkV1'],
    '/api/app/v1/groups-with-fallback'               => ['GET',  AppGroupController::class.'::groupsWithFallbackV1'],
    '/api/app/v1/main/{cid}'                         => ['GET',  CharController::class.'::mainV1'],
    '/api/app/v2/main/{cid}'                         => ['GET',  CharController::class.'::mainV2'],
    '/api/app/v1/player/{characterId}'               => ['GET',  CharController::class.'::playerV1'],
    '/api/app/v1/characters/{characterId}'           => ['GET',  CharController::class.'::charactersV1'],
    '/api/app/v1/player-chars/{playerId}'            => ['GET',  CharController::class.'::playerCharactersV1'],
    '/api/app/v1/removed-characters/{characterId}'   => ['GET',  CharController::class.'::removedCharactersV1'],
    '/api/app/v1/corp-players/{corporationId}'       => ['GET',  CharController::class.'::corporationPlayersV1'],
    '/api/app/v1/corporation/{id}/member-tracking'   => ['GET',  AppCorporationController::class.'::memberTrackingV1'],
    '/api/app/v1/esi[{path:.*}]'                     => [
                                                            'GET'  => AppEsiController::class.'::esiV1',
                                                            'POST' => AppEsiController::class.'::esiPostV1',
                                                        ],

    '/api/user/app/all'                       => ['GET',    AppController::class.'::all'],
    '/api/user/app/create'                    => ['POST',   AppController::class.'::create'],
    '/api/user/app/{id}/show'                 => ['GET',    AppController::class.'::show'],
    '/api/user/app/{id}/rename'               => ['PUT',    AppController::class.'::rename'],
    '/api/user/app/{id}/delete'               => ['DELETE', AppController::class.'::delete'],
    '/api/user/app/{id}/add-group/{gid}'      => ['PUT',    AppController::class.'::addGroup'],
    '/api/user/app/{id}/remove-group/{gid}'   => ['PUT',    AppController::class.'::removeGroup'],
    '/api/user/app/{id}/managers'             => ['GET',    AppController::class.'::managers'],
    '/api/user/app/{id}/add-manager/{pid}'    => ['PUT',    AppController::class.'::addManager'],
    '/api/user/app/{id}/remove-manager/{pid}' => ['PUT',    AppController::class.'::removeManager'],
    '/api/user/app/{id}/add-role/{name}'      => ['PUT',    AppController::class.'::addRole'],
    '/api/user/app/{id}/remove-role/{name}'   => ['PUT',    AppController::class.'::removeRole'],
    '/api/user/app/{id}/change-secret'        => ['PUT',    AppController::class.'::changeSecret'],

    '/api/user/alliance/all'                     => ['GET',  AllianceController::class.'::all'],
    '/api/user/alliance/with-groups'             => ['GET',  AllianceController::class.'::withGroups'],
    '/api/user/alliance/add/{id}'                => ['POST', AllianceController::class.'::add'],
    '/api/user/alliance/{id}/add-group/{gid}'    => ['PUT',  AllianceController::class.'::addGroup'],
    '/api/user/alliance/{id}/remove-group/{gid}' => ['PUT',  AllianceController::class.'::removeGroup'],

    '/api/user/auth/callback' => ['GET',  AuthController::class.'::callback'], // only for backwards compatibility
    '/api/user/auth/result'   => ['GET',  AuthController::class.'::result'],
    '/api/user/auth/logout'   => ['POST', AuthController::class.'::logout'],

    '/api/user/character/find-by/{name}'      => ['GET',  CharacterController::class.'::findBy'],
    '/api/user/character/show'                => ['GET',  CharacterController::class.'::show'],
    '/api/user/character/{id}/update'         => ['PUT',  CharacterController::class.'::update'],

    '/api/user/corporation/all'                                  => ['GET',  CorporationController::class.'::all'],
    '/api/user/corporation/with-groups'                          => ['GET',  CorporationController::class.'::withGroups'],
    '/api/user/corporation/add/{id}'                             => ['POST', CorporationController::class.'::add'],
    '/api/user/corporation/{id}/add-group/{gid}'                 => ['PUT',  CorporationController::class.'::addGroup'],
    '/api/user/corporation/{id}/remove-group/{gid}'              => ['PUT',  CorporationController::class.'::removeGroup'],
    '/api/user/corporation/{id}/get-groups-tracking'             => ['GET',  CorporationController::class.'::getGroupsTracking'],
    '/api/user/corporation/{id}/add-group-tracking/{groupId}'    => ['PUT',  CorporationController::class.'::addGroupTracking'],
    '/api/user/corporation/{id}/remove-group-tracking/{groupId}' => ['PUT',  CorporationController::class.'::removeGroupTracking'],
    '/api/user/corporation/tracked-corporations'                 => ['GET',  CorporationController::class.'::trackedCorporations'],
    '/api/user/corporation/{id}/members'                         => ['GET',  CorporationController::class.'::members'],

    '/api/user/esi/request' => ['GET', EsiController::class.'::request'],

    '/api/user/group/all'                            => ['GET',    GroupController::class.'::all'],
    '/api/user/group/public'                         => ['GET',    GroupController::class.'::public'],
    '/api/user/group/create'                         => ['POST',   GroupController::class.'::create'],
    '/api/user/group/{id}/rename'                    => ['PUT',    GroupController::class.'::rename'],
    '/api/user/group/{id}/set-visibility/{choice}'   => ['PUT',    GroupController::class.'::setVisibility'],
    '/api/user/group/{id}/delete'                    => ['DELETE', GroupController::class.'::delete'],
    '/api/user/group/{id}/managers'                  => ['GET',    GroupController::class.'::managers'],
    '/api/user/group/{id}/corporations'              => ['GET',    GroupController::class.'::corporations'],
    '/api/user/group/{id}/alliances'                 => ['GET',    GroupController::class.'::alliances'],
    '/api/user/group/{id}/required-groups'           => ['GET',    GroupController::class.'::requiredGroups'],
    '/api/user/group/{id}/add-required/{groupId}'    => ['PUT',    GroupController::class.'::addRequiredGroup'],
    '/api/user/group/{id}/remove-required/{groupId}' => ['PUT',    GroupController::class.'::removeRequiredGroup'],
    '/api/user/group/{id}/add-manager/{pid}'         => ['PUT',    GroupController::class.'::addManager'],
    '/api/user/group/{id}/remove-manager/{pid}'      => ['PUT',    GroupController::class.'::removeManager'],
    '/api/user/group/{id}/applications'              => ['GET',    GroupController::class.'::applications'],
    '/api/user/group/accept-application/{id}'        => ['PUT',    GroupController::class.'::acceptApplication'],
    '/api/user/group/deny-application/{id}'          => ['PUT',    GroupController::class.'::denyApplication'],
    '/api/user/group/{id}/add-member/{pid}'          => ['PUT',    GroupController::class.'::addMember'],
    '/api/user/group/{id}/remove-member/{pid}'       => ['PUT',    GroupController::class.'::removeMember'],
    '/api/user/group/{id}/members'                   => ['GET',    GroupController::class.'::members'],

    '/api/user/player/with-characters'          => ['GET',    PlayerController::class.'::withCharacters'],
    '/api/user/player/without-characters'       => ['GET',    PlayerController::class.'::withoutCharacters'],
    '/api/user/player/invalid-token'            => ['GET',    PlayerController::class.'::invalidToken'],
    '/api/user/player/no-token'                 => ['GET',    PlayerController::class.'::noToken'],
    '/api/user/player/show'                     => ['GET',    PlayerController::class.'::show'],
    '/api/user/player/{id}/groups-disabled'     => ['GET',    PlayerController::class.'::groupsDisabledById'],
    '/api/user/player/groups-disabled'          => ['GET',    PlayerController::class.'::groupsDisabled'],
    '/api/user/player/add-application/{gid}'    => ['PUT',    PlayerController::class.'::addApplication'],
    '/api/user/player/remove-application/{gid}' => ['PUT',    PlayerController::class.'::removeApplication'],
    '/api/user/player/show-applications'        => ['GET',    PlayerController::class.'::showApplications'],
    '/api/user/player/leave-group/{gid}'        => ['PUT',    PlayerController::class.'::leaveGroup'],
    '/api/user/player/set-main/{cid}'           => ['PUT',    PlayerController::class.'::setMain'],
    '/api/user/player/delete-character/{id}'    => ['DELETE', PlayerController::class.'::deleteCharacter'],
    '/api/user/player/app-managers'             => ['GET',    PlayerController::class.'::appManagers'],
    '/api/user/player/group-managers'           => ['GET',    PlayerController::class.'::groupManagers'],
    '/api/user/player/{id}/set-status/{status}' => ['PUT',    PlayerController::class.'::setStatus'],
    '/api/user/player/{id}/add-role/{name}'     => ['PUT',    PlayerController::class.'::addRole'],
    '/api/user/player/{id}/remove-role/{name}'  => ['PUT',    PlayerController::class.'::removeRole'],
    '/api/user/player/{id}/show'                => ['GET',    PlayerController::class.'::showById'],
    '/api/user/player/{id}/characters'          => ['GET',    PlayerController::class.'::characters'],
    '/api/user/player/with-role/{name}'         => ['GET',    PlayerController::class.'::withRole'],
    '/api/user/player/with-status/{name}'       => ['GET',    PlayerController::class.'::withStatus'],

    '/api/user/settings/system/theme'                    => ['GET',  SettingsController::class.'::theme'],
    '/api/user/settings/system/list'                     => ['GET',  SettingsController::class.'::systemList'],
    '/api/user/settings/system/change/{name}'            => ['PUT',  SettingsController::class.'::systemChange'],
    '/api/user/settings/system/send-invalid-token-mail'  => ['POST', SettingsController::class.'::sendInvalidTokenMail'],
    '/api/user/settings/system/validate-director/{name}' => ['PUT',  SettingsController::class.'::validateDirector'],

    '/api/user/watchlist/{id}/players'                          => ['GET',  WatchlistController::class.'::players'],
    '/api/user/watchlist/{id}/exemption/list'                   => ['GET',  WatchlistController::class.'::exemptionList'],
    '/api/user/watchlist/{id}/exemption/add/{player}'           => ['PUT',  WatchlistController::class.'::exemptionAdd'],
    '/api/user/watchlist/{id}/exemption/remove/{player}'        => ['PUT',  WatchlistController::class.'::exemptionRemove'],
    '/api/user/watchlist/{id}/corporation/list'                 => ['GET',  WatchlistController::class.'::corporationList'],
    '/api/user/watchlist/{id}/corporation/add/{corporation}'    => ['PUT',  WatchlistController::class.'::corporationAdd'],
    '/api/user/watchlist/{id}/corporation/remove/{corporation}' => ['PUT',  WatchlistController::class.'::corporationRemove'],
    '/api/user/watchlist/{id}/alliance/list'                    => ['GET',  WatchlistController::class.'::allianceList'],
    '/api/user/watchlist/{id}/alliance/add/{alliance}'          => ['PUT',  WatchlistController::class.'::allianceAdd'],
    '/api/user/watchlist/{id}/alliance/remove/{alliance}'       => ['PUT',  WatchlistController::class.'::allianceRemove'],
    '/api/user/watchlist/{id}/group/list'                       => ['GET',  WatchlistController::class.'::groupList'],
    '/api/user/watchlist/{id}/group/add/{group}'                => ['PUT',  WatchlistController::class.'::groupAdd'],
    '/api/user/watchlist/{id}/group/remove/{group}'             => ['PUT',  WatchlistController::class.'::groupRemove'],
];
