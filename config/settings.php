<?php
use Brave\Core\Application;
use Monolog\Logger;

return [

    // Settings that can be customized by users
    'settings.httpVersion' => '1.1',
    'settings.responseChunkSize' => 4096,
    'settings.outputBuffering' => 'append',
    'settings.determineRouteBeforeAppMiddleware' => false,
    'settings.displayErrorDetails' => false,
    'settings.addContentLengthHeader' => false, // Allow the web server to send the content-length header
    'settings.routerCacheFile' => false,

    'config' => [

        'twig' => [
            'template_path' => Application::ROOT_DIR . '/src/templates/',
            'cache' => Application::ROOT_DIR . '/var/cache/twig'
        ],

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
                'url' => getenv('DATABASE_URL')
            ]
        ]
    ],
];
