<?php

/**
 * Required roles for routes.
 *
 * First match will be used, matched by "starts-with"
 */
return [
    // add necessary exceptions to /api/user route
    '/api/user/auth/login' => ['role.anonymous', 'role.user'],
    '/api/user/auth/callback' => ['role.anonymous', 'role.user'],
    '/api/user/auth/result' => ['role.anonymous', 'role.user'],

    '/api/user' => ['role.user'],
    '/api/app' => ['role.app']
];
