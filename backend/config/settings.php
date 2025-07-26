<?php

declare(strict_types=1);

use Neucore\Application;

return [

    'env_var_defaults' => [
        'NEUCORE_EVE_DATASOURCE'    => 'tranquility',
        'NEUCORE_USER_AGENT'        => 'Neucore/' . NEUCORE_VERSION . ' (https://github.com/tkhamez/neucore)',
        'NEUCORE_LOG_PATH'          => Application::ROOT_DIR . '/var/logs',
        'NEUCORE_LOG_ROTATION'      => 'weekly',
        'NEUCORE_LOG_FORMAT'        => 'multiline',
        'NEUCORE_CACHE_DIR'         => Application::ROOT_DIR . '/var/cache',
        'NEUCORE_SESSION_SECURE'    => '1',
        'NEUCORE_ERROR_REPORTING'   => (string)(E_ALL & ~E_DEPRECATED),
        'NEUCORE_RATE_LIMIT_MAX'   => '0',
        'NEUCORE_RATE_LIMIT_TIME'   => '0',
    ],

    'monolog' => [
        'path'     => '${NEUCORE_LOG_PATH}',
        'rotation' => '${NEUCORE_LOG_ROTATION}',
        'format'   => '${NEUCORE_LOG_FORMAT}',
    ],

    'doctrine' => [
        'meta' => [
            'entity_paths' => [
                Application::ROOT_DIR . '/src/Entity'
            ],
            'dev_mode' => false,
            'proxy_dir' =>  '${NEUCORE_CACHE_DIR}/proxies'
        ],
        'connection' => [
            'url' => '${NEUCORE_DATABASE_URL}'
        ],
        'driver_options' => [
            'mysql_ssl_ca'             => '${NEUCORE_MYSQL_SSL_CA}',
            'mysql_verify_server_cert' => '${NEUCORE_MYSQL_VERIFY_SERVER_CERT}',
        ],
    ],

    'error_reporting' => '${NEUCORE_ERROR_REPORTING}',

    'repository' => 'https://github.com/tkhamez/neucore',

    'CORS' => [
        'allow_origin' => '${NEUCORE_ALLOW_ORIGIN}',
    ],

    'session' => [
        'secure'    => '${NEUCORE_SESSION_SECURE}',
    ],

    'eve' => [
        'client_id'    => '${NEUCORE_EVE_CLIENT_ID}',
        'secret_key'   => '${NEUCORE_EVE_SECRET_KEY}',
        'callback_url' => '${NEUCORE_EVE_CALLBACK_URL}',
        'scopes'       => '${NEUCORE_EVE_SCOPES}',
        'datasource'   => '${NEUCORE_EVE_DATASOURCE}',
        'esi_host'     => 'https://esi.evetech.net',
        'oauth_urls'   => [], // only used for tests
        'oauth_verify_signature' => true,
        'esi_compatibility_date' => '2025-08-02',
    ],

    'guzzle' => [
        'cache' => [
            'dir' => '${NEUCORE_CACHE_DIR}/http'
        ],
        'user_agent' => '${NEUCORE_USER_AGENT}',
    ],

    'di' => [
        'cache_dir' => '${NEUCORE_CACHE_DIR}/di'
    ],

    'rate_limit' => [
        'max' => '${NEUCORE_RATE_LIMIT_MAX}',
        'time' => '${NEUCORE_RATE_LIMIT_TIME}',
    ],

    'plugins_install_dir' => '${NEUCORE_PLUGINS_INSTALL_DIR}',
];
