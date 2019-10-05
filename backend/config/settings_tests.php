<?php declare(strict_types=1);

/**
 *  Test settings, overwrites values from settings.php (and settings_dev.php if running in dev mode).
 */

return [
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
];
