<?php declare(strict_types=1);

use Brave\Core\Application;
use GuzzleHttp\Client;
use Monolog\Logger;

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
            'name' => 'app',
            'path' => Application::ROOT_DIR . '/var/logs/app-'.date('o\wW').'.log', // weekly logs
            'level' => Logger::DEBUG,
        ],

        'doctrine' => [
            'meta' => [
                'entity_paths' => [
                    Application::ROOT_DIR . '/src/classes/Brave/Core/Entity'
                ],
                'dev_mode' => false,
                'proxy_dir' =>  Application::ROOT_DIR . '/var/cache/proxies'
            ],
            'connection' => [
                'url' => getenv('BRAVECORE_DATABASE_URL')
            ]
        ],

        'CORS' => [
            'allow_origin' => [] // e. g. https://frontend.domain.tld
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

        'session' => [
            'gc_maxlifetime' => 1440 // 24 minutes
        ],

        'guzzle' => [
            'cache' => [
                'dir' => Application::ROOT_DIR . '/var/cache/http'
            ],
            'user_agent' => 'Neucore/' . BRAVE_CORE_VERSION . ' (https://github.com/tkhamez/neucore) ' .
                            'GuzzleHttp/' . Client::VERSION,
        ],

        'di' => [
            'cache_dir' => Application::ROOT_DIR . '/var/cache/di'
        ]
    ],
];
