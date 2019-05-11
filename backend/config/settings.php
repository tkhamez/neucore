<?php declare(strict_types=1);

use Neucore\Application;
use GuzzleHttp\Client;

return [

    // Slim framework settings that can be customized by users
    'settings.httpVersion' => '1.1',
    'settings.responseChunkSize' => 4096,
    'settings.outputBuffering' => 'append',
    'settings.determineRouteBeforeAppMiddleware' => true,
    'settings.displayErrorDetails' => false,
    'settings.addContentLengthHeader' => false,
    'settings.routerCacheFile' => false,

    'config' => [

        'monolog' => [
            'path' => (getenv('BRAVECORE_LOG_PATH') ?: Application::ROOT_DIR . '/var/logs') .
                '/app-' . (
                    getenv('BRAVECORE_LOG_ROTATION') === 'd' ? date('Ymd') : (
                        getenv('BRAVECORE_LOG_ROTATION') === 'm' ? date('Ym') : date('o\wW')
                    )
                ) . '.log',
        ],

        'doctrine' => [
            'meta' => [
                'entity_paths' => [
                    Application::ROOT_DIR . '/src/classes/Entity'
                ],
                'dev_mode' => false,
                'proxy_dir' =>  Application::ROOT_DIR . '/var/cache/proxies'
            ],
            'connection' => [
                'url' => getenv('BRAVECORE_DATABASE_URL')
            ],
            'driver_options' => [
                'mysql_ssl_ca'             => getenv('BRAVECORE_MYSQL_SSL_CA'),
                'mysql_verify_server_cert' => getenv('BRAVECORE_MYSQL_VERIFY_SERVER_CERT'),
            ],
            'data_fixtures' => Application::ROOT_DIR . '/src/classes/DataFixtures'
        ],

        'CORS' => [
            'allow_origin' => getenv('BRAVECORE_ALLOW_ORIGIN'),
        ],

        'eve' => [
            'client_id'    => getenv('BRAVECORE_EVE_CLIENT_ID'),
            'secret_key'   => getenv('BRAVECORE_EVE_SECRET_KEY'),
            'callback_url' => getenv('BRAVECORE_EVE_CALLBACK_URL'),
            'scopes'       => getenv('BRAVECORE_EVE_SCOPES'),
            'datasource'   => getenv('BRAVECORE_EVE_DATASOURCE') ?: 'tranquility',
            'esi_host'     => 'https://esi.evetech.net',
            'sso_domain_tq'   => 'login.eveonline.com',
            'sso_domain_sisi' => 'sisilogin.testeveonline.com',
        ],

        'guzzle' => [
            'cache' => [
                'dir' => Application::ROOT_DIR . '/var/cache/http'
            ],
            'user_agent' => 'Neucore/' . NEUCORE_VERSION . ' (https://github.com/tkhamez/neucore) ' .
                            'GuzzleHttp/' . Client::VERSION,
        ],

        'di' => [
            'cache_dir' => Application::ROOT_DIR . '/var/cache/di'
        ]
    ],
];
