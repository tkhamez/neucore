<?php

/**
 * Required roles for routes.
 *
 * First match will be used, matched by "starts-with"
 */
return [
    // add necessary exceptions to /api/user route
    '/api/user/auth/login-alt' => ['user'],
    '/api/user/auth/login' => ['anonymous', 'user'],
    '/api/user/auth/callback' => ['anonymous', 'user'],
    '/api/user/auth/result' => ['anonymous', 'user'],

    '/api/user' => ['user'],
    '/api/app' => ['app']
];
