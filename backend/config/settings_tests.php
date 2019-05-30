<?php declare(strict_types=1);

/**
 *  Test settings, loaded additionally to other settings files.
 */

return [
    'config' => [
        'monolog' => [
            'path' => 'php://stderr',
        ],
        'doctrine' => [
            'connection' => [
                'url' => '${BRAVECORE_TEST_DATABASE_URL}'
            ],
            'driver_options' => [
                'mysql_ssl_ca'             => '${BRAVECORE_TEST_MYSQL_SSL_CA}',
                'mysql_verify_server_cert' => '${BRAVECORE_TEST_MYSQL_VERIFY_SERVER_CERT}',
            ],
        ],
        'eve' => [
            'oauth_urls_tq' => [
                'authorize' => 'https://localhost/oauth/authorize',
                'token'     => 'https://localhost/oauth/token',
                'verify'    => 'https://localhost/oauth/verify',
            ],
        ],
    ],
];
