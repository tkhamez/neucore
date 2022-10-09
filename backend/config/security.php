<?php

declare(strict_types=1);

use Neucore\Entity\Role;

/**
 * Required roles for routes.
 *
 * First match will be used, matched by "starts-with"
 */
return [
    '/plugin/{id}/{name}'       => [Role::ANONYMOUS, Role::USER],

    '/api/app/v1/main'                             => [Role::APP_CHARS],
    '/api/app/v2/main'                             => [Role::APP_CHARS],
    '/api/app/v1/player/'                          => [Role::APP_CHARS],
    '/api/app/v1/character-list'                   => [Role::APP_CHARS],
    '/api/app/v1/characters'                       => [Role::APP_CHARS],
    '/api/app/v1/player-chars'                     => [Role::APP_CHARS],
    '/api/app/v1/player-with-characters'           => [Role::APP_CHARS],
    '/api/app/v1/removed-characters'               => [Role::APP_CHARS],
    '/api/app/v1/incoming-characters'              => [Role::APP_CHARS],
    '/api/app/v1/corp-players'                     => [Role::APP_CHARS],
    '/api/app/v1/corp-characters'                  => [Role::APP_CHARS],

    '/api/app/v1/corporation/{id}/member-tracking' => [Role::APP_TRACKING],

    '/api/app/v1/esi'                              => [Role::APP_ESI],
    '/api/app/v2/esi'                              => [Role::APP_ESI],

    '/api/app/v1/groups'                           => [Role::APP_GROUPS],
    '/api/app/v2/groups'                           => [Role::APP_GROUPS],
    '/api/app/v1/corp-groups'                      => [Role::APP_GROUPS],
    '/api/app/v2/corp-groups'                      => [Role::APP_GROUPS],
    '/api/app/v1/alliance-groups'                  => [Role::APP_GROUPS],
    '/api/app/v2/alliance-groups'                  => [Role::APP_GROUPS],
    '/api/app/v1/groups-with-fallback'             => [Role::APP_GROUPS],
    '/api/app/v1/group-members/{groupId}'          => [Role::APP_GROUPS],

    '/api/app'                                     => [Role::APP], // only showV1

    '/api/user/alliance/find'      => [Role::GROUP_ADMIN, Role::WATCHLIST_MANAGER, Role::SETTINGS],
    '/api/user/alliance/alliances' => [Role::GROUP_ADMIN, Role::WATCHLIST_MANAGER, Role::SETTINGS],
    '/api/user/alliance/add/{id}'  => [Role::GROUP_ADMIN, Role::WATCHLIST_MANAGER],
    '/api/user/alliance'           => [Role::GROUP_ADMIN],

    '/api/user/app/{id}/change-secret' => [Role::APP_MANAGER],
    '/api/user/app/{id}/show'          => [Role::APP_MANAGER, Role::APP_ADMIN],
    '/api/user/app'                    => [Role::APP_ADMIN],

    '/api/user/auth/callback'   => [Role::ANONYMOUS, Role::USER], // only for backwards compatibility
    '/api/user/auth/result'     => [Role::ANONYMOUS, Role::USER],
    '/api/user/auth/csrf-token' => [Role::USER],

    '/api/user/character/find-character' => [Role::USER_ADMIN, Role::USER_MANAGER, Role::USER_CHARS],
    '/api/user/character/find-player'    => [Role::GROUP_MANAGER],
    '/api/user/character/{id}/update'    => [Role::USER],

    '/api/user/corporation/tracked-corporations'       => [Role::TRACKING],
    '/api/user/corporation/all-tracked-corporations'   => [Role::TRACKING_ADMIN],
    '/api/user/corporation/{id}/members'               => [Role::TRACKING],
    '/api/user/corporation/{id}/tracking-director'     => [Role::TRACKING_ADMIN],
    '/api/user/corporation/{id}/get-groups-tracking'   => [Role::TRACKING_ADMIN],
    '/api/user/corporation/{id}/add-group-tracking'    => [Role::TRACKING_ADMIN],
    '/api/user/corporation/{id}/remove-group-tracking' => [Role::TRACKING_ADMIN],
    '/api/user/corporation/find'                       => [Role::GROUP_ADMIN, Role::WATCHLIST_MANAGER, Role::SETTINGS],
    '/api/user/corporation/corporations'               => [Role::GROUP_ADMIN, Role::WATCHLIST_MANAGER, Role::SETTINGS],
    '/api/user/corporation/add/{id}'                   => [Role::GROUP_ADMIN, Role::WATCHLIST_MANAGER],
    '/api/user/corporation'                            => [Role::GROUP_ADMIN],

    '/api/user/esi/request' => [Role::ESI],

    '/api/user/group/public'                  => [Role::USER],
    '/api/user/group/all'                     => [
                                                    Role::APP_ADMIN, Role::GROUP_ADMIN,
                                                    Role::USER_MANAGER, Role::WATCHLIST_ADMIN
                                                ],
    '/api/user/group/{id}/applications'       => [Role::GROUP_MANAGER],
    '/api/user/group/accept-application/{id}' => [Role::GROUP_MANAGER],
    '/api/user/group/deny-application/{id}'   => [Role::GROUP_MANAGER],
    '/api/user/group/{id}/add-member'         => [Role::GROUP_MANAGER, Role::USER_MANAGER],
    '/api/user/group/{id}/remove-member'      => [Role::GROUP_MANAGER, Role::USER_MANAGER],
    '/api/user/group/{id}/members'            => [Role::GROUP_ADMIN, Role::GROUP_MANAGER],
    '/api/user/group/{id}/required-groups'    => [Role::GROUP_MANAGER, Role::GROUP_ADMIN],
    '/api/user/group/{id}/forbidden-groups'   => [Role::GROUP_MANAGER, Role::GROUP_ADMIN],
    '/api/user/group/{id}/managers'           => [Role::GROUP_MANAGER, Role::GROUP_ADMIN],
    '/api/user/group'                         => [Role::GROUP_ADMIN],

    '/api/user/player/show'                        => [Role::USER], // includes /show-applications
    '/api/user/player/groups-disabled'             => [Role::USER],
    '/api/user/player/add-application'             => [Role::USER],
    '/api/user/player/remove-application'          => [Role::USER],
    '/api/user/player/leave-group'                 => [Role::USER],
    '/api/user/player/set-main'                    => [Role::USER],
    '/api/user/player/delete-character/{id}'       => [Role::USER, Role::USER_ADMIN],
    '/api/user/player/app-managers'                => [Role::APP_ADMIN],
    '/api/user/player/group-managers'              => [Role::GROUP_ADMIN],
    '/api/user/player/{id}/characters'             => [
                                                       Role::APP_ADMIN, Role::GROUP_ADMIN, Role::USER_MANAGER,
                                                       Role::USER_CHARS, Role::WATCHLIST, Role::TRACKING
                                                   ],
    '/api/user/player/group-characters-by-account' => [Role::USER_CHARS],
    '/api/user/player/with-status/{name}'          => [Role::USER_ADMIN, Role::USER_MANAGER],
    '/api/user/player/{id}/show'                   => [Role::USER_ADMIN, Role::USER_MANAGER],
    '/api/user/player/{id}/set-status/'            => [Role::USER_MANAGER],
    '/api/user/player'                             => [Role::USER_ADMIN],

    '/api/user/role'                               => [Role::USER_ADMIN],

    '/api/user/settings/system/list'        => [Role::ANONYMOUS, Role::USER],
    '/api/user/settings/system'             => [Role::SETTINGS],
    '/api/user/settings/eve-login/list'     => [Role::USER],
    '/api/user/settings/eve-login'          => [Role::SETTINGS],

    '/api/user/watchlist/list-available-manage'           => [Role::WATCHLIST_MANAGER],
    '/api/user/watchlist/list-available'                  => [Role::WATCHLIST],
    '/api/user/watchlist/{id}/players'                    => [Role::WATCHLIST],
    '/api/user/watchlist/{id}/players-kicklist'           => [Role::WATCHLIST],
    '/api/user/watchlist/{id}/exemption/list'             => [Role::WATCHLIST, Role::WATCHLIST_MANAGER],
    '/api/user/watchlist/{id}/corporation/list'           => [Role::WATCHLIST, Role::WATCHLIST_MANAGER, Role::WATCHLIST_ADMIN],
    '/api/user/watchlist/{id}/alliance/list'              => [Role::WATCHLIST, Role::WATCHLIST_MANAGER, Role::WATCHLIST_ADMIN],
    '/api/user/watchlist/{id}/kicklist-corporation/list'  => [Role::WATCHLIST, Role::WATCHLIST_MANAGER],
    '/api/user/watchlist/{id}/kicklist-alliance/list'     => [Role::WATCHLIST, Role::WATCHLIST_MANAGER],
    '/api/user/watchlist/{id}/allowlist-corporation/list' => [Role::WATCHLIST, Role::WATCHLIST_MANAGER],
    '/api/user/watchlist/{id}/allowlist-alliance/list'    => [Role::WATCHLIST, Role::WATCHLIST_MANAGER],
    '/api/user/watchlist/{id}/exemption/'                 => [Role::WATCHLIST_MANAGER], # add, remove
    '/api/user/watchlist/{id}/corporation/'               => [Role::WATCHLIST_MANAGER, Role::WATCHLIST_ADMIN], # add, remove
    '/api/user/watchlist/{id}/alliance/'                  => [Role::WATCHLIST_MANAGER, Role::WATCHLIST_ADMIN], # add, remove
    '/api/user/watchlist/{id}/kicklist-corporation/'      => [Role::WATCHLIST_MANAGER], # add, remove
    '/api/user/watchlist/{id}/kicklist-alliance/'         => [Role::WATCHLIST_MANAGER], # add, remove
    '/api/user/watchlist/{id}/allowlist-corporation/'     => [Role::WATCHLIST_MANAGER], # add, remove
    '/api/user/watchlist/{id}/allowlist-alliance/'        => [Role::WATCHLIST_MANAGER], # add, remove
    '/api/user/watchlist'                                 => [Role::WATCHLIST_ADMIN], # several admin functions

    '/api/user/service-admin/'              => [Role::SERVICE_ADMIN],
    '/api/user/service/update-all-accounts' => [Role::USER_ADMIN, Role::USER_MANAGER, Role::GROUP_ADMIN,
                                                Role::APP_ADMIN, Role::USER_CHARS, Role::TRACKING, Role::WATCHLIST],
    '/api/user/service/'                    => [Role::USER],

    '/api/user/statistics/'     => [Role::STATISTICS],

    '/api/user' => [Role::USER],
];
