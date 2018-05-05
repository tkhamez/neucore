<?php declare(strict_types=1);

use Brave\Core\Application;
use Monolog\Logger;

return [

    // Settings that can be customized by users
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
            'path' => Application::ROOT_DIR . '/var/logs/app.log',
            'level' => Logger::DEBUG,
        ],

        'doctrine' => [
            'meta' => [
                'entity_path' => [
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
            'client_id' => getenv('BRAVECORE_EVE_CLIENT_ID'),
            'secret_key' => getenv('BRAVECORE_EVE_SECRET_KEY'),
            'callback_url' => getenv('BRAVECORE_EVE_CALLBACK_URL'),
            'scopes' => getenv('BRAVECORE_EVE_SCOPES')
        ],

        'session' => [
            'gc_maxlifetime' => 7200 // 2 hours
        ]
    ],
];
