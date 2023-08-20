<?php

declare(strict_types=1);

/**
 *  Test settings, overwrites values from settings.php (and settings_dev.php if running in dev mode).
 */

return [
    'monolog' => [
        'path' => 'php://stderr',
    ],
    'doctrine' => [
        'connection' => [
            'url' => '${NEUCORE_TEST_DATABASE_URL}'
        ],
        'driver_options' => [
            'mysql_ssl_ca'             => '${NEUCORE_TEST_MYSQL_SSL_CA}',
            'mysql_verify_server_cert' => '${NEUCORE_TEST_MYSQL_VERIFY_SERVER_CERT}',
        ],
    ],
    'eve' => [
        'oauth_urls'   => [
            'metadata'  => 'https://login.eveonline.com/.well-known/oauth-authorization-server',
            'authorize' => 'https://login.eveonline.com/v2/oauth/authorize',
            'token'     => 'https://login.eveonline.com/v2/oauth/token',
            'jwks'      => 'https://login.eveonline.com/oauth/jwks',
            'revoke'    => 'https://login.eveonline.com/v2/oauth/revoke',
            'issuer'    => 'login.eveonline.com',
        ],
    ],
];
