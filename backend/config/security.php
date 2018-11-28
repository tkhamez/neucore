<?php declare(strict_types=1);

use Brave\Core\Entity\Role;

/**
 * Required roles for routes.
 *
 * First match will be used, matched by "starts-with"
 */
return [
    '/api/app' => [Role::APP],

    '/api/user/alliance' => [Role::GROUP_ADMIN],

    '/api/user/app/{id}/change-secret' => [Role::APP_MANAGER],
    '/api/user/app/{id}/groups'        => [Role::APP_MANAGER, Role::APP_ADMIN],
    '/api/user/app'                    => [Role::APP_ADMIN],

    '/api/user/auth/login'     => [Role::ANONYMOUS, Role::USER],
    '/api/user/auth/callback'  => [Role::ANONYMOUS, Role::USER],
    '/api/user/auth/result'    => [Role::ANONYMOUS, Role::USER],

    '/api/user/character/find-by'        => [Role::USER_ADMIN, Role::GROUP_MANAGER],
    '/api/user/character/find-player-of' => [Role::USER_ADMIN, Role::GROUP_MANAGER],
    '/api/user/character/{id}/update'    => [Role::USER, Role::USER_ADMIN],

    '/api/user/corporation' => [Role::GROUP_ADMIN],

    '/api/user/esi/request' => [Role::ESI],

    '/api/user/group/public'                => [Role::USER],
    '/api/user/group/all'                   => [Role::APP_ADMIN, Role::GROUP_ADMIN],
    '/api/user/group/{id}/applicants'       => [Role::GROUP_MANAGER],
    '/api/user/group/{id}/remove-applicant' => [Role::GROUP_MANAGER],
    '/api/user/group/{id}/add-member'       => [Role::GROUP_MANAGER],
    '/api/user/group/{id}/remove-member'    => [Role::GROUP_MANAGER],
    '/api/user/group/{id}/members'          => [Role::GROUP_MANAGER],
    '/api/user/group'                       => [Role::GROUP_ADMIN],

    '/api/user/player/show'                  => [Role::USER],
    '/api/user/player/add-application'       => [Role::USER],
    '/api/user/player/remove-application'    => [Role::USER],
    '/api/user/player/leave-group'           => [Role::USER],
    '/api/user/player/set-main'              => [Role::USER],
    '/api/user/player/delete-character/{id}' => [Role::USER],
    '/api/user/player/app-managers'          => [Role::APP_ADMIN],
    '/api/user/player/group-managers'        => [Role::GROUP_ADMIN],
    '/api/user/player/with-characters'       => [Role::USER_ADMIN],
    '/api/user/player/without-characters'    => [Role::USER_ADMIN],
    '/api/user/player/{id}/characters'       => [Role::APP_ADMIN, Role::GROUP_ADMIN, Role::GROUP_MANAGER],
    '/api/user/player'                       => [Role::USER_ADMIN],

    '/api/user/settings/system/list' => [Role::ANONYMOUS, Role::USER],
    '/api/user/settings/system'      => [Role::SETTINGS],

    '/api/user' => [Role::USER],
];
