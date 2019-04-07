<?php declare(strict_types=1);

use Brave\Core\Entity\Role;

/**
 * Required roles for routes.
 *
 * First match will be used, matched by "starts-with"
 */
return [
    '/api/app/v1/corporation/{id}/member-tracking' => [Role::APP_TRACKING],
    '/api/app/v1/esi'                              => [Role::APP_ESI],

    '/api/app/v1/groups'                           => [Role::APP_GROUPS],
    '/api/app/v2/groups'                           => [Role::APP_GROUPS],
    '/api/app/v1/corp-groups'                      => [Role::APP_GROUPS],
    '/api/app/v2/corp-groups'                      => [Role::APP_GROUPS],
    '/api/app/v1/alliance-groups'                  => [Role::APP_GROUPS],
    '/api/app/v2/alliance-groups'                  => [Role::APP_GROUPS],
    '/api/app/v1/groups-with-fallback'             => [Role::APP_GROUPS],

    '/api/app/v1/main'                             => [Role::APP_CHARS],
    '/api/app/v2/main'                             => [Role::APP_CHARS],
    '/api/app/v1/characters'                       => [Role::APP_CHARS],

    '/api/app'                                     => [Role::APP],

    '/api/user/alliance/all' => [Role::GROUP_ADMIN, Role::SETTINGS],
    '/api/user/alliance'     => [Role::GROUP_ADMIN],

    '/api/user/app/{id}/change-secret' => [Role::APP_MANAGER],
    '/api/user/app/{id}/show'          => [Role::APP_MANAGER, Role::APP_ADMIN],
    '/api/user/app'                    => [Role::APP_ADMIN],

    '/api/user/auth/callback' => [Role::ANONYMOUS, Role::USER], // only for backwards compatibility
    '/api/user/auth/result'   => [Role::ANONYMOUS, Role::USER],

    '/api/user/character/find-by'        => [Role::USER_ADMIN, Role::GROUP_MANAGER],
    '/api/user/character/find-player-of' => [Role::USER_ADMIN, Role::GROUP_MANAGER],
    '/api/user/character/{id}/update'    => [Role::USER, Role::USER_ADMIN],

    '/api/user/corporation/tracked-corporations' => [Role::TRACKING],
    '/api/user/corporation/{id}/members'         => [Role::TRACKING],
    '/api/user/corporation'                      => [Role::GROUP_ADMIN],

    '/api/user/esi/request' => [Role::ESI],

    '/api/user/group/public'                  => [Role::USER],
    '/api/user/group/all'                     => [Role::APP_ADMIN, Role::GROUP_ADMIN, Role::USER_ADMIN],
    '/api/user/group/{id}/applications'       => [Role::GROUP_MANAGER],
    '/api/user/group/accept-application/{id}' => [Role::GROUP_MANAGER],
    '/api/user/group/deny-application/{id}'   => [Role::GROUP_MANAGER],
    '/api/user/group/{id}/add-member'         => [Role::GROUP_MANAGER, Role::USER_ADMIN],
    '/api/user/group/{id}/remove-member'      => [Role::GROUP_MANAGER, Role::USER_ADMIN],
    '/api/user/group/{id}/members'            => [Role::GROUP_MANAGER],
    '/api/user/group'                         => [Role::GROUP_ADMIN],

    '/api/user/player/show'                  => [Role::USER], // includes /show-applications
    '/api/user/player/groups-disabled'       => [Role::USER],
    '/api/user/player/add-application'       => [Role::USER],
    '/api/user/player/remove-application'    => [Role::USER],
    '/api/user/player/leave-group'           => [Role::USER],
    '/api/user/player/set-main'              => [Role::USER],
    '/api/user/player/delete-character/{id}' => [Role::USER],
    '/api/user/player/app-managers'          => [Role::APP_ADMIN],
    '/api/user/player/group-managers'        => [Role::GROUP_ADMIN],
    '/api/user/player/{id}/characters'       => [Role::APP_ADMIN, Role::GROUP_ADMIN, Role::GROUP_MANAGER, Role::TRACKING],
    '/api/user/player'                       => [Role::USER_ADMIN],

    '/api/user/settings/system/list' => [Role::ANONYMOUS, Role::USER],
    '/api/user/settings/system'      => [Role::SETTINGS],

    '/api/user' => [Role::USER],
];
