<?php

declare(strict_types=1);

use Neucore\Application;
use GuzzleHttp\Client;

return [

    'env_var_defaults' => [
        'NEUCORE_EVE_DATASOURCE' => 'tranquility',
        'NEUCORE_LOG_PATH'       => Application::ROOT_DIR . '/var/logs',
        'NEUCORE_LOG_ROTATION'   => 'weekly',
        'NEUCORE_LOG_FORMAT'     => 'multiline',
        'NEUCORE_CACHE_DIR'      => Application::ROOT_DIR . '/var/cache',
        'NEUCORE_SESSION_SECURE' => 1,
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

    'CORS' => [
        'allow_origin' => '${NEUCORE_ALLOW_ORIGIN}',
    ],

    'session' => [
        'secure' => '${NEUCORE_SESSION_SECURE}',
    ],

    'eve' => [
        'client_id'       => '${NEUCORE_EVE_CLIENT_ID}',
        'secret_key'      => '${NEUCORE_EVE_SECRET_KEY}',
        'callback_url'    => '${NEUCORE_EVE_CALLBACK_URL}',
        'scopes'          => '${NEUCORE_EVE_SCOPES}',
        'datasource'      => '${NEUCORE_EVE_DATASOURCE}',
        'esi_host'        => 'https://esi.evetech.net',
        'oauth_urls_tq'   => [
            'authorize' => 'https://login.eveonline.com/v2/oauth/authorize',
            'token'     => 'https://login.eveonline.com/v2/oauth/token',
            'verify'    => '', // only needed for SSO v1, but required by class
            'jwks'      => 'https://login.eveonline.com/oauth/jwks',
            'revoke'    => 'https://login.eveonline.com/v2/oauth/revoke',
        ],
        'oauth_urls_sisi' => [
            'authorize' => 'https://sisilogin.testeveonline.com/v2/oauth/authorize',
            'token'     => 'https://sisilogin.testeveonline.com/v2/oauth/token',
            'verify'    => '',
            'jwks'      => 'https://sisilogin.testeveonline.com/oauth/jwks',
            'revoke'    => 'https://sisilogin.testeveonline.com/v2/oauth/revoke',
        ],
    ],

    'guzzle' => [
        'cache' => [
            'dir' => '${NEUCORE_CACHE_DIR}/http'
        ],
        'user_agent' => 'Neucore/' . NEUCORE_VERSION . ' (https://github.com/tkhamez/neucore) ' .
                        'GuzzleHttp/' . Client::VERSION,
    ],

    'di' => [
        'cache_dir' => '${NEUCORE_CACHE_DIR}/di'
    ],
];
