<?php

use Brave\Core\Roles;

/**
 * Required roles for routes.
 *
 * First match will be used, matched by "starts-with"
 */
return [
    '/api/app' => [Roles::APP],

    '/api/user/application/{id}/change-secret' => [Roles::APP_MANAGER],
    '/api/user/application'                    => [Roles::APP_ADMIN],

    '/api/user/auth/login-alt' => [Roles::USER],
    '/api/user/auth/login'     => [Roles::ANONYMOUS, Roles::USER],
    '/api/user/auth/callback'  => [Roles::ANONYMOUS, Roles::USER],
    '/api/user/auth/result'    => [Roles::ANONYMOUS, Roles::USER],

    '/api/user/group/all'                    => [Roles::USER, Roles::GROUP_ADMIN, Roles::APP_ADMIN],
    '/api/api/user/group/{id}/applicants'    => [Roles::GROUP_MANAGER],
    '/api/api/user/group/{id}/add-member'    => [Roles::GROUP_MANAGER],
    '/api/api/user/group/{id}/remove-member' => [Roles::GROUP_MANAGER],
    '/api/user/group'                        => [Roles::GROUP_ADMIN],

    '/api/user/player/add-application'    => [Roles::USER],
    '/api/user/player/remove-application' => [Roles::USER],
    '/api/user/player/remove-group'       => [Roles::USER],
    '/api/user/player/app-managers'       => [Roles::APP_ADMIN],
    '/api/user/player/group-managers'     => [Roles::GROUP_ADMIN],
    '/api/user/player'                    => [Roles::USER_ADMIN],

    '/api/user' => [Roles::USER],
];
