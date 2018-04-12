<?php

/**
 * Required roles for routes.
 *
 * First match will be used, matched by "starts-with"
 */
return [
    // add necessary exceptions to /api/user/auth routes
    '/api/user/auth/login-alt' =>   ['user'],
    '/api/user/auth/login' =>       ['anonymous', 'user'],
    '/api/user/auth/callback' =>    ['anonymous', 'user'],
    '/api/user/auth/result' =>      ['anonymous', 'user'],

    '/api/user/player/list' =>      ['user-admin'],

    '/api/user/role' =>             ['user-admin'],

    '/api/user' =>  ['user'],

    '/api/app' =>   ['app']
];
