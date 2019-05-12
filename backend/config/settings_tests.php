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
                'url' => getenv('BRAVECORE_TEST_DATABASE_URL')
            ],
            'driver_options' => [
                'mysql_ssl_ca'             => getenv('BRAVECORE_TEST_MYSQL_SSL_CA'),
                'mysql_verify_server_cert' => getenv('BRAVECORE_TEST_MYSQL_VERIFY_SERVER_CERT'),
            ],
        ],
        'eve' => [
            'sso_domain_tq'   => 'localhost',
        ],
    ],
];
