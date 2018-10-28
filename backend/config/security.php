<?php declare(strict_types=1);

use Brave\Core\Roles;

/**
 * Required roles for routes.
 *
 * First match will be used, matched by "starts-with"
 */
return [
    '/api/app' => [Roles::APP],

    '/api/user/alliance' => [Roles::GROUP_ADMIN],

    '/api/user/app/{id}/change-secret' => [Roles::APP_MANAGER],
    '/api/user/app/{id}/groups'        => [Roles::APP_MANAGER, Roles::APP_ADMIN],
    '/api/user/app'                    => [Roles::APP_ADMIN],

    '/api/user/auth/login-alt' => [Roles::USER],
    '/api/user/auth/login'     => [Roles::ANONYMOUS, Roles::USER],
    '/api/user/auth/callback'  => [Roles::ANONYMOUS, Roles::USER],
    '/api/user/auth/result'    => [Roles::ANONYMOUS, Roles::USER],

    '/api/user/character/find-by'        => [Roles::USER_ADMIN, Roles::GROUP_MANAGER],
    '/api/user/character/find-player-of' => [Roles::USER_ADMIN, Roles::GROUP_MANAGER],
    '/api/user/character/{id}/update'    => [Roles::USER, Roles::USER_ADMIN],

    '/api/user/corporation' => [Roles::GROUP_ADMIN],

    '/api/user/esi/request' => [Roles::ESI],

    '/api/user/group/public'                => [Roles::USER],
    '/api/user/group/all'                   => [Roles::APP_ADMIN, Roles::GROUP_ADMIN],
    '/api/user/group/{id}/applicants'       => [Roles::GROUP_MANAGER],
    '/api/user/group/{id}/remove-applicant' => [Roles::GROUP_MANAGER],
    '/api/user/group/{id}/add-member'       => [Roles::GROUP_MANAGER],
    '/api/user/group/{id}/remove-member'    => [Roles::GROUP_MANAGER],
    '/api/user/group/{id}/members'          => [Roles::GROUP_MANAGER],
    '/api/user/group'                       => [Roles::GROUP_ADMIN],

    '/api/user/player/show'                  => [Roles::USER],
    '/api/user/player/add-application'       => [Roles::USER],
    '/api/user/player/remove-application'    => [Roles::USER],
    '/api/user/player/leave-group'           => [Roles::USER],
    '/api/user/player/set-main'              => [Roles::USER],
    '/api/user/player/delete-character/{id}' => [Roles::USER],
    '/api/user/player/app-managers'          => [Roles::APP_ADMIN],
    '/api/user/player/group-managers'        => [Roles::GROUP_ADMIN],
    '/api/user/player/all'                   => [Roles::USER_ADMIN, Roles::GROUP_MANAGER],
    '/api/user/player/{id}/characters'       => [Roles::APP_ADMIN, Roles::GROUP_ADMIN, Roles::GROUP_MANAGER],
    '/api/user/player'                       => [Roles::USER_ADMIN],

    '/api/user' => [Roles::USER],
];
